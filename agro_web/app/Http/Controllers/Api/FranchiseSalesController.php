<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FranchiseSalesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Sale::where('franchise_id', $user->franchise_id)
            ->with(['customer', 'items.product'])
            ->latest();

        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('sale_date', [$request->from_date, $request->to_date]);
        }

        $sales = $query->paginate(20);

        return response()->json($sales);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'price_tier' => 'nullable|string|in:retailer,farmer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,mobile_money,bank_transfer,credit',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $sale = SaleService::create($request->user(), $request->all());

        return response()->json([
            'message' => 'Sale recorded successfully.',
            'data' => $sale,
        ], 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        $user = request()->user();

        if ($sale->franchise_id !== $user->franchise_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $sale->load(['customer', 'items.product', 'creator']);

        return response()->json(['data' => $sale]);
    }
}
