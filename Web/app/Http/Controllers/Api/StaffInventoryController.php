<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FranchiseInventory;
use App\Models\StockMovement;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffInventoryController extends Controller
{
    public function warehouseStock(): JsonResponse
    {
        $stock = WarehouseInventory::with('product.category')->latest()->paginate(50);

        return response()->json($stock);
    }

    public function franchiseStock(): JsonResponse
    {
        $stock = FranchiseInventory::with(['franchise', 'product.category'])->latest()->paginate(50);

        return response()->json($stock);
    }

    public function updateWarehouseStock(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
        ]);

        $existing = WarehouseInventory::where('product_id', $request->product_id)->first();
        $previousQuantity = $existing?->quantity ?? 0;
        $quantityChange = $request->quantity - $previousQuantity;

        $inventory = WarehouseInventory::updateOrCreate(
            ['product_id' => $request->product_id],
            [
                'quantity' => $request->quantity,
                'reorder_level' => $request->reorder_level ?? 0,
                'last_restocked_at' => now(),
            ]
        );

        if ($quantityChange != 0) {
            StockMovement::log($quantityChange > 0 ? 'warehouse_in' : 'adjustment', $request->product_id, $quantityChange, 0, WarehouseInventory::class, $inventory->id, 'Manual stock update by staff', $request->user()->id);
        }

        $inventory->load('product');

        return response()->json(['message' => 'Warehouse stock updated.', 'data' => $inventory]);
    }
}
