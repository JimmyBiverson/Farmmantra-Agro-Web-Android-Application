<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\Message;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $now = now();
        $today = $now->toDateString();

        $awaitingPayment = Order::where('status', 'approved')
            ->where('delivery_status', 'pending')
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'accepted'))
            ->count();

        $deliveriesToday = Order::where('delivery_status', 'out_for_delivery')
            ->whereDate('served_at', $today)
            ->orWhere(function ($q) use ($today) {
                $q->whereDate('expected_delivery_date', $today)
                    ->whereIn('delivery_status', ['out_for_delivery', 'ready_for_delivery', 'delivered']);
            })
            ->count();

        $data = [
            'summary' => [
                'pending_orders' => Order::where('status', 'pending')->count(),
                'overdue_dispatch_orders' => Order::dispatchOverdue()->count(),
                'dispatch_target_days' => Order::dispatchTargetDays(),
                'can_manage_orders' => $request->user()->canManageOrders(),
                'pending_orders_today' => Order::where('status', 'pending')
                    ->whereDate('created_at', $today)
                    ->count(),
                'awaiting_payment_orders' => $awaitingPayment,
                'deliveries_today' => $deliveriesToday,
                'approved_orders_today' => Order::where('status', 'approved')
                    ->whereDate('approved_at', $today)
                    ->count(),
                'total_orders_this_month' => Order::whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->count(),
                'declined_orders_this_month' => Order::where('status', 'declined')
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->count(),
                'total_orders_value_this_month' => Order::whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->sum('total_amount'),
                'low_stock_products' => WarehouseInventory::whereColumn('quantity', '<=', 'reorder_level')
                    ->count(),
                'out_of_stock_products' => WarehouseInventory::where('quantity', '<=', 0)->count(),
                'new_support_messages' => Message::where('is_read', false)
                    ->whereHas('conversation', function ($q) {
                        $q->where('status', 'open');
                    })
                    ->count(),
                'pending_payments_count' => PaymentSubmission::where('status', 'pending')->count(),
                'total_warehouse_value' => WarehouseInventory::join('products', 'products.id', '=', 'warehouse_inventories.product_id')
                    ->sum(DB::raw('warehouse_inventories.quantity * products.standard_price')),
                'active_franchises' => Franchise::where('is_active', true)->count(),
            ],

            'banner' => [
                'pending_orders_today' => Order::where('status', 'pending')
                    ->whereDate('created_at', $today)
                    ->count(),
                'awaiting_payment' => $awaitingPayment,
                'deliveries_today' => $deliveriesToday,
                'warehouse_stock_alerts' => WarehouseInventory::whereColumn('quantity', '<=', 'reorder_level')
                    ->with('product:id,name,sku')
                    ->get()
                    ->map(fn ($i) => [
                        'product_id' => $i->product_id,
                        'product_name' => $i->product?->name,
                        'sku' => $i->product?->sku,
                        'quantity' => $i->quantity,
                        'reorder_level' => $i->reorder_level,
                        'is_out' => $i->quantity <= 0,
                    ]),
                'new_support_messages' => Message::where('is_read', false)
                    ->whereHas('conversation', function ($q) {
                        $q->where('status', 'open');
                    })
                    ->count(),
            ],

            'pending_orders_list' => Order::where('status', 'pending')
                ->with(['franchise:id,name,code', 'items.product:id,name,sku'])
                ->latest()
                ->limit(10)
                ->get(),

            'recent_approved_orders' => Order::where('status', 'approved')
                ->with(['franchise:id,name,code', 'items.product:id,name'])
                ->latest()
                ->limit(5)
                ->get(),

            'warehouse_stock' => WarehouseInventory::with('product:id,name,sku,unit_of_measure')
                ->get()
                ->map(function ($item) {
                    return [
                        'product_name' => $item->product?->name,
                        'sku' => $item->product?->sku,
                        'unit' => $item->product?->unit_of_measure,
                        'quantity' => $item->quantity,
                        'reserved' => $item->reserved_quantity,
                        'available' => $item->quantity - $item->reserved_quantity,
                        'reorder_level' => $item->reorder_level,
                        'is_low' => $item->quantity <= $item->reorder_level,
                    ];
                }),

            'orders_by_status' => Order::select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get(),

            'orders_trend' => Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as total_value')
            )
                ->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay())
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get(),
        ];

        return response()->json(['data' => $data]);
    }
}
