<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->withCount('products')
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function products(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'priceSlabs', 'warehouseInventory', 'images'])
            ->where('is_active', true);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->paginate(50);

        return response()->json($products);
    }

    public function product(Product $product): JsonResponse
    {
        $product->load(['category', 'priceSlabs', 'warehouseInventory', 'images']);

        return response()->json(['data' => $product]);
    }

    public function priceSlabs(Product $product): JsonResponse
    {
        $slabs = $product->priceSlabs()
            ->where('is_active', true)
            ->orderBy('min_quantity')
            ->get();

        return response()->json($slabs);
    }
}
