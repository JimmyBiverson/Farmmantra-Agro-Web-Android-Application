<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StockReceipt;
use App\Services\OrderService;
use Illuminate\Http\Request;

/**
 * Order operations on the web dashboard. Sales staff see these pages in
 * read-only mode; the Warehouse role (and admin) accept, decline, dispatch
 * and confirm arrival. Franchise partners accept delivered orders here too.
 */
class OperationsController extends Controller
{
    use HandlesWebActions;

    public function orders(Request $request)
    {
        $query = Order::with(['franchise:id,name,code', 'items.product:id,name', 'dispatchedByUser:id,name', 'approvedByUser:id,name'])
            ->withCount(['acceptedPayments as payment_accepted_count']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }
        if ($request->filled('franchise_id')) {
            $query->where('franchise_id', $request->franchise_id);
        }
        if ($request->boolean('overdue')) {
            $query->dispatchOverdue();
        }

        $orders = $query->latest()->paginate(20)->withQueryString();
        $franchises = Franchise::orderBy('name')->get(['id', 'name']);
        $overdueCount = Order::dispatchOverdue()->count();
        $targetDays = Order::dispatchTargetDays();
        $canManage = $request->user()->canManageOrders();
        $dispatchMeans = Order::DISPATCH_MEANS;

        return view('staff.orders', compact('orders', 'franchises', 'overdueCount', 'targetDays', 'canManage', 'dispatchMeans'));
    }

    public function approve(Request $request, Order $order)
    {
        $request->validate([
            'expected_delivery_date' => 'required|date|after:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->attempt(
            fn () => OrderService::approve($order->load('items.product'), $request->user(), $request->expected_delivery_date, $request->notes),
            fn ($o) => "Order {$o->order_number} approved (signature {$o->approval_signature}).",
        );
    }

    public function decline(Request $request, Order $order)
    {
        $request->validate(['decline_reason' => 'required|string|min:3|max:500']);

        return $this->attempt(
            fn () => OrderService::decline($order, $request->user(), $request->decline_reason),
            "Order {$order->order_number} declined.",
        );
    }

    public function dispatch(Request $request, Order $order)
    {
        $request->validate([
            'dispatch_means' => 'required|in:'.implode(',', array_keys(Order::DISPATCH_MEANS)),
            'dispatch_vehicle' => 'nullable|string|max:100',
            'dispatch_remarks' => 'nullable|string|max:1000',
        ]);

        return $this->attempt(
            fn () => OrderService::dispatch($order, $request->user(), $request->dispatch_means, $request->dispatch_remarks, $request->dispatch_vehicle),
            fn ($o) => "Order {$o->order_number} dispatched (signature {$o->dispatch_signature}).",
        );
    }

    public function markDelivered(Request $request, Order $order)
    {
        return $this->attempt(
            fn () => OrderService::markDelivered($order, $request->user()),
            "Order {$order->order_number} marked as arrived. The franchise must now accept it.",
        );
    }

    // ── Franchise: accept a delivered order ─────────────────────
    public function receiveForm(Request $request, Order $order)
    {
        abort_unless($order->franchise_id === $request->user()->franchise_id, 403);
        $order->load(['items.product', 'stockReceipt.items.product', 'dispatchedByUser:id,name']);
        $terms = Setting::get('terms_and_conditions', '');

        return view('franchise.receive', compact('order', 'terms'));
    }

    public function receive(Request $request, Order $order)
    {
        abort_unless($order->franchise_id === $request->user()->franchise_id, 403);
        $request->validate([
            'accept_terms' => 'accepted',
            'items' => 'required|array',
            'items.*.stock_receipt_item_id' => 'required|integer',
            'items.*.received_quantity' => 'required|numeric|min:0',
            'items.*.discrepancy_notes' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ], ['accept_terms.accepted' => 'You must read and accept the terms and conditions.']);

        $receipt = StockReceipt::where('order_id', $order->id)->firstOrFail();

        return $this->attempt(
            fn () => OrderService::receive($receipt, $request->user(), array_values($request->items), $request->notes, true)
                ? redirect()->route('web.franchise.orders')->with('success', "Delivery of {$order->order_number} accepted. Your stock has been updated.")
                : null,
            '',
        );
    }
}
