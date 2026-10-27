<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FranchiseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::forFranchise($user->franchise_id)
            ->with('items.product.category')
            ->withCount(['acceptedPayments as payment_accepted_count']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        $orders = $query->latest()->paginate(20);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_terms' => 'nullable|string|in:credit,cash',
            'notes' => 'nullable|string',
        ]);

        $order = OrderService::place($request->user(), $request->items, $request->notes, $request->input('payment_terms', 'credit'));

        return response()->json([
            'message' => 'Order placed successfully.',
            'data' => $order,
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $user = request()->user();

        if ($order->franchise_id !== $user->franchise_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $order->loadCount(['acceptedPayments as payment_accepted_count']);
        $order->load([
            'items.product.category',
            'stockReceipt.items.product',
            'approvedByUser:id,name',
            'dispatchedByUser:id,name',
            'signatures',
            'payments' => fn ($q) => $q->where('status', 'accepted')->latest('accepted_at'),
        ]);

        return response()->json(['data' => $order]);
    }

    /**
     * Franchise partner accepts a delivered order. Without an explicit item
     * list every dispatched quantity is accepted as received. Requires the
     * terms & conditions to be accepted (accept_terms = true).
     */
    public function confirmDelivery(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($order->franchise_id !== $user->franchise_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'accept_terms' => 'accepted',
            'items' => 'nullable|array',
            'items.*.stock_receipt_item_id' => 'required_with:items',
            'items.*.received_quantity' => 'required_with:items|numeric|min:0',
            'items.*.discrepancy_notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $receipt = $order->stockReceipt;
        if (! $receipt) {
            return response()->json(['message' => 'This order has no delivery to accept yet.'], 422);
        }

        $items = $request->input('items') ?? $receipt->items->map(fn ($i) => [
            'stock_receipt_item_id' => $i->id,
            'received_quantity' => $i->ordered_quantity,
        ])->all();

        OrderService::receive($receipt, $user, $items, $request->notes, $request->boolean('accept_terms'));

        return response()->json([
            'message' => 'Delivery accepted. Your stock and account have been updated.',
            'data' => $order->fresh(['franchise', 'items.product.category', 'stockReceipt.items.product']),
        ]);
    }
}
