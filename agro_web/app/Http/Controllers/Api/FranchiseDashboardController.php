<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Sale;
use App\Models\SalesTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FranchiseDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $franchiseId = $user->franchise_id;
        $now = now();

        $monthSales = Sale::where('franchise_id', $franchiseId)
            ->whereMonth('sale_date', $now->month)
            ->whereYear('sale_date', $now->year);
        $monthRevenue = (float) (clone $monthSales)->sum('final_amount');
        $monthCost = (float) (clone $monthSales)->sum('total_cost');

        $data = [
            'summary' => [
                'sales_count_this_month' => (clone $monthSales)->count(),
                'profit_this_month' => round($monthRevenue - $monthCost, 2),
                'cost_of_sales_this_month' => round($monthCost, 2),
                'overdue_dispatch_orders' => Order::where('franchise_id', $franchiseId)->dispatchOverdue()->count(),
                'awaiting_acceptance' => Order::where('franchise_id', $franchiseId)->whereIn('delivery_status', ['out_for_delivery', 'delivered'])->count(),
                'total_sales_this_month' => Sale::where('franchise_id', $franchiseId)
                    ->whereMonth('sale_date', $now->month)
                    ->whereYear('sale_date', $now->year)
                    ->sum('final_amount'),
                'total_sales_last_month' => Sale::where('franchise_id', $franchiseId)
                    ->whereMonth('sale_date', $now->copy()->subMonth()->month)
                    ->whereYear('sale_date', $now->copy()->subMonth()->year)
                    ->sum('final_amount'),
                'total_sales_ytd' => Sale::where('franchise_id', $franchiseId)
                    ->whereYear('sale_date', $now->year)
                    ->sum('final_amount'),
                'total_inventory_value' => FranchiseInventory::where('franchise_id', $franchiseId)
                    ->sum('total_value'),
                'inventory_items_count' => FranchiseInventory::where('franchise_id', $franchiseId)
                    ->where('quantity', '>', 0)
                    ->count(),
                'pending_orders' => Order::where('franchise_id', $franchiseId)
                    ->where('status', 'pending')
                    ->count(),
                'pending_payments' => PaymentSubmission::where('franchise_id', $franchiseId)
                    ->where('status', 'pending')
                    ->count(),
                'outstanding_balance' => $user->franchise?->account_balance ?? 0,
                'credit_limit' => $user->franchise?->credit_limit ?? 0,
                'credit_used_percentage' => $user->franchise?->credit_limit > 0
                    ? round(($user->franchise->account_balance / $user->franchise->credit_limit) * 100, 1)
                    : 0,
                'low_stock_items' => FranchiseInventory::where('franchise_id', $franchiseId)
                    ->whereColumn('quantity', '<=', 'reorder_level')
                    ->count(),
            ],

            'sales_by_category' => DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->where('sales.franchise_id', $franchiseId)
                ->whereMonth('sales.sale_date', $now->month)
                ->whereYear('sales.sale_date', $now->year)
                ->select('categories.name as category_name', DB::raw('SUM(sale_items.subtotal) as total_sales'), DB::raw('SUM(sale_items.quantity) as total_qty'))
                ->groupBy('categories.name')
                ->orderByDesc('total_sales')
                ->get(),

            'sales_trend' => Sale::where('franchise_id', $franchiseId)
                ->select(
                    DB::raw('DATE(sale_date) as date'),
                    DB::raw('SUM(final_amount) as total_sales'),
                    DB::raw('COUNT(*) as sale_count')
                )
                ->where('sale_date', '>=', $now->copy()->subDays(30)->startOfDay())
                ->groupBy(DB::raw('DATE(sale_date)'))
                ->orderBy('date')
                ->get(),

            'top_products' => DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('sales.franchise_id', $franchiseId)
                ->whereMonth('sales.sale_date', $now->month)
                ->whereYear('sales.sale_date', $now->year)
                ->select('products.id', 'products.name', 'products.sku', DB::raw('SUM(sale_items.quantity) as total_qty'), DB::raw('SUM(sale_items.subtotal) as total_revenue'))
                ->groupBy('products.id', 'products.name', 'products.sku')
                ->orderByDesc('total_revenue')
                ->limit(10)
                ->get(),

            'recent_orders' => Order::where('franchise_id', $franchiseId)
                ->with(['items.product:id,name'])
                ->latest()
                ->limit(5)
                ->get(),

            'sales_targets' => SalesTarget::where('franchise_id', $franchiseId)
                ->where('month', $now->month)
                ->where('year', $now->year)
                ->with('productCategory:id,name')
                ->get()
                ->map(function ($target) use ($franchiseId, $now) {
                    $actualSales = Sale::where('franchise_id', $franchiseId)
                        ->whereMonth('sale_date', $now->month)
                        ->whereYear('sale_date', $now->year);
                    if ($target->product_category_id) {
                        $actualSales = $actualSales->whereHas('items.product', function ($q) use ($target) {
                            $q->where('category_id', $target->product_category_id);
                        });
                    }
                    $actual = $actualSales->sum('final_amount');

                    return [
                        'target_id' => $target->id,
                        'category' => $target->productCategory?->name ?? 'All Products',
                        'target_amount' => $target->target_amount,
                        'actual_amount' => $actual,
                        'achievement_percentage' => $target->target_amount > 0
                            ? round(($actual / $target->target_amount) * 100, 1)
                            : 0,
                    ];
                }),

            'inventory_status' => FranchiseInventory::where('franchise_id', $franchiseId)
                ->with('product:id,name,sku,selling_price')
                ->get()
                ->map(function ($item) {
                    return [
                        'product_name' => $item->product?->name,
                        'sku' => $item->product?->sku,
                        'quantity' => $item->quantity,
                        'reorder_level' => $item->reorder_level,
                        'is_low_stock' => $item->quantity <= $item->reorder_level,
                        'value' => $item->quantity * ($item->product?->selling_price ?? 0),
                    ];
                }),

            'recent_payments' => PaymentSubmission::where('franchise_id', $franchiseId)
                ->latest('submitted_at')
                ->limit(5)
                ->get(['id', 'payment_number', 'amount', 'status', 'submitted_at']),
        ];

        return response()->json(['data' => $data]);
    }
}
