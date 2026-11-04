<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FranchiseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FranchiseInventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $inventory = FranchiseInventory::where('franchise_id', $user->franchise_id)
            ->with('product.category')
            ->latest()
            ->paginate(50);

        return response()->json($inventory);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $user = $request->user();

        $inventory = FranchiseInventory::where('franchise_id', $user->franchise_id)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->with('product.category')
            ->get();

        return response()->json(['data' => $inventory]);
    }
}
