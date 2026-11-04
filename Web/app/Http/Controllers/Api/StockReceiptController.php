<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockReceipt;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $receipts = StockReceipt::where('franchise_id', $user->franchise_id)
            ->with(['order', 'items.product'])
            ->latest()
            ->get();

        return response()->json(['data' => $receipts]);
    }

    public function show(StockReceipt $stockReceipt): JsonResponse
    {
        $user = request()->user();

        if ($stockReceipt->franchise_id !== $user->franchise_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $stockReceipt->load(['order', 'items.product', 'receiver']);

        return response()->json(['data' => $stockReceipt]);
    }

    public function confirm(Request $request, StockReceipt $stockReceipt): JsonResponse
    {
        $user = $request->user();

        if ($stockReceipt->franchise_id !== $user->franchise_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'accept_terms' => 'accepted',
            'items' => 'required|array',
            'items.*.stock_receipt_item_id' => 'required|exists:stock_receipt_items,id',
            'items.*.received_quantity' => 'required|numeric|min:0',
            'items.*.discrepancy_notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $receipt = OrderService::receive($stockReceipt, $user, $request->input('items'), $request->notes, $request->boolean('accept_terms'));

        return response()->json([
            'message' => 'Delivery accepted. Inventory updated.',
            'data' => $receipt,
        ]);
    }
}
