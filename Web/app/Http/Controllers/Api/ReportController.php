<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Sale;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salesReport(Request $request): JsonResponse
    {
        $request->validate([
            'franchise_id' => 'nullable|exists:franchises,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $dateFrom = $request->date_from ? now()->parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = $request->date_to ? now()->parse($request->date_to)->endOfDay() : now()->endOfDay();
        $categoryId = $request->category_id;

        $query = Sale::query()
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->with(['franchise:id,name,code', 'creator:id,name']);

        if ($request->franchise_id) {
            $query->where('franchise_id', $request->franchise_id);
        }
        if ($categoryId) {
            $query->whereHas('items.product', fn ($q) => $q->where('category_id', $categoryId));
        }

        $sales = $query->latest('sale_date')->paginate(50);

        $summary = Sale::whereBetween('sale_date', [$dateFrom, $dateTo])
            ->when($request->franchise_id, fn ($q) => $q->where('franchise_id', $request->franchise_id))
            ->when($categoryId, fn ($q) => $q->whereHas('items.product', fn ($itemQuery) => $itemQuery->where('category_id', $categoryId)))
            ->select(
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(total_amount) as gross_sales'),
                DB::raw('SUM(discount) as total_discounts'),
                DB::raw('SUM(final_amount) as net_sales'),
                DB::raw('AVG(final_amount) as avg_sale_value'),
            )
            ->first();

        $byCategory = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.sale_date', [$dateFrom, $dateTo])
            ->when($request->franchise_id, fn ($q) => $q->where('sales.franchise_id', $request->franchise_id))
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->select('categories.name as category', DB::raw('SUM(sale_items.quantity) as qty'), DB::raw('SUM(sale_items.subtotal) as revenue'))
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->get();

        $byFranchise = Sale::whereBetween('sale_date', [$dateFrom, $dateTo])
            ->when($categoryId, fn ($q) => $q->whereHas('items.product', fn ($itemQuery) => $itemQuery->where('category_id', $categoryId)))
            ->select('franchise_id', DB::raw('COUNT(*) as transactions'), DB::raw('SUM(final_amount) as revenue'))
            ->groupBy('franchise_id')
            ->with('franchise:id,name,code')
            ->orderByDesc('revenue')
            ->get();

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.sale_date', [$dateFrom, $dateTo])
            ->when($request->franchise_id, fn ($q) => $q->where('sales.franchise_id', $request->franchise_id))
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->select('products.id', 'products.name', 'products.sku', DB::raw('SUM(sale_items.quantity) as qty'), DB::raw('SUM(sale_items.subtotal) as revenue'))
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'summary' => $summary,
                'by_category' => $byCategory,
                'by_franchise' => $byFranchise,
                'top_products' => $topProducts,
                'transactions' => $sales,
            ],
        ]);
    }

    public function paymentReport(Request $request): JsonResponse
    {
        $request->validate([
            'franchise_id' => 'nullable|exists:franchises,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|in:pending,verified,accepted,rejected',
        ]);

        $dateFrom = $request->date_from ? now()->parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = $request->date_to ? now()->parse($request->date_to)->endOfDay() : now()->endOfDay();

        $query = PaymentSubmission::whereBetween('submitted_at', [$dateFrom, $dateTo])
            ->with('franchise:id,name,code');

        if ($request->franchise_id) {
            $query->where('franchise_id', $request->franchise_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest('submitted_at')->paginate(50);

        $summary = PaymentSubmission::whereBetween('submitted_at', [$dateFrom, $dateTo])
            ->when($request->franchise_id, fn ($q) => $q->where('franchise_id', $request->franchise_id))
            ->select(
                DB::raw('COUNT(*) as total_submissions'),
                DB::raw('SUM(amount) as total_submitted'),
                DB::raw('SUM(CASE WHEN status = "accepted" THEN verified_amount ELSE 0 END) as total_accepted'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN amount ELSE 0 END) as total_rejected'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN amount ELSE 0 END) as total_pending'),
            )
            ->first();

        $byStatus = PaymentSubmission::whereBetween('submitted_at', [$dateFrom, $dateTo])
            ->when($request->franchise_id, fn ($q) => $q->where('franchise_id', $request->franchise_id))
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('status')
            ->get();

        $byFranchise = PaymentSubmission::whereBetween('submitted_at', [$dateFrom, $dateTo])
            ->select('franchise_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'), DB::raw('SUM(CASE WHEN status = "accepted" THEN verified_amount ELSE 0 END) as accepted'))
            ->groupBy('franchise_id')
            ->with('franchise:id,name,code')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'data' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'summary' => $summary,
                'by_status' => $byStatus,
                'by_franchise' => $byFranchise,
                'transactions' => $payments,
            ],
        ]);
    }

    public function inventoryReport(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|in:warehouse,franchise',
            'franchise_id' => 'nullable|exists:franchises,id',
        ]);

        $type = $request->type ?? 'warehouse';

        if ($type === 'warehouse') {
            $stock = WarehouseInventory::with('product:id,name,sku,standard_price,unit_of_measure,category_id')
                ->when($request->has('low_stock_only'), fn ($q) => $q->whereColumn('quantity', '<=', 'reorder_level'))
                ->get();

            $summary = [
                'total_products' => $stock->count(),
                'total_quantity' => $stock->sum('quantity'),
                'total_reserved' => $stock->sum('reserved_quantity'),
                'total_value' => $stock->sum(fn ($s) => $s->quantity * ($s->product?->standard_price ?? 0)),
                'low_stock_count' => $stock->filter(fn ($s) => $s->quantity <= $s->reorder_level)->count(),
                'out_of_stock_count' => $stock->filter(fn ($s) => $s->quantity <= 0)->count(),
            ];
        } else {
            $query = FranchiseInventory::with(['product:id,name,sku,unit_of_measure', 'franchise:id,name,code']);
            if ($request->franchise_id) {
                $query->where('franchise_id', $request->franchise_id);
            }
            if ($request->has('low_stock_only')) {
                $query->whereColumn('quantity', '<=', 'reorder_level');
            }

            $stock = $query->get();

            $summary = [
                'total_items' => $stock->count(),
                'total_quantity' => $stock->sum('quantity'),
                'total_value' => $stock->sum('total_value'),
                'low_stock_count' => $stock->filter(fn ($s) => $s->quantity <= $s->reorder_level)->count(),
            ];
        }

        return response()->json([
            'data' => [
                'type' => $type,
                'summary' => $summary,
                'items' => $stock,
            ],
        ]);
    }

    public function orderReport(Request $request): JsonResponse
    {
        $request->validate([
            'franchise_id' => 'nullable|exists:franchises,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|in:pending,approved,declined,delivered',
        ]);

        $dateFrom = $request->date_from ? now()->parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = $request->date_to ? now()->parse($request->date_to)->endOfDay() : now()->endOfDay();

        $query = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->with(['franchise:id,name,code', 'items.product:id,name']);

        if ($request->franchise_id) {
            $query->where('franchise_id', $request->franchise_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(50);

        $summary = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($request->franchise_id, fn ($q) => $q->where('franchise_id', $request->franchise_id))
            ->select(
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_value'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                DB::raw('SUM(CASE WHEN status = "declined" THEN 1 ELSE 0 END) as declined_count'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_count'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
            )
            ->first();

        $summary->fulfillment_rate = $summary->total_orders > 0
            ? round((($summary->approved_count + $summary->delivered_count) / $summary->total_orders) * 100, 1)
            : 0;

        $byFranchise = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('franchise_id', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total_amount) as value'), DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'))
            ->groupBy('franchise_id')
            ->with('franchise:id,name,code')
            ->orderByDesc('value')
            ->get();

        return response()->json([
            'data' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'summary' => $summary,
                'by_franchise' => $byFranchise,
                'transactions' => $orders,
            ],
        ]);
    }

    public function franchiseComparison(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $dateFrom = $request->date_from ? now()->parse($request->date_from)->startOfMonth() : now()->startOfMonth();
        $dateTo = $request->date_to ? now()->parse($request->date_to)->endOfDay() : now()->endOfDay();

        $franchises = Franchise::where('is_active', true)
            ->withCount(['orders as total_orders' => fn ($q) => $q->whereBetween('created_at', [$dateFrom, $dateTo])])
            ->withSum(['orders as total_order_value' => fn ($q) => $q->whereBetween('created_at', [$dateFrom, $dateTo])], 'total_amount')
            ->withCount(['sales as total_sales' => fn ($q) => $q->whereBetween('sale_date', [$dateFrom, $dateTo])])
            ->withSum(['sales as total_sales_value' => fn ($q) => $q->whereBetween('sale_date', [$dateFrom, $dateTo])], 'final_amount')
            ->get()
            ->map(function ($f) {
                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'code' => $f->code,
                    'total_orders' => $f->total_orders,
                    'total_order_value' => $f->total_order_value ?? 0,
                    'total_sales' => $f->total_sales,
                    'total_sales_value' => $f->total_sales_value ?? 0,
                    'outstanding_balance' => $f->account_balance,
                ];
            })
            ->sortByDesc('total_sales_value')
            ->values();

        return response()->json(['data' => $franchises]);
    }
}
