<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product', 'user']);
        $user = $request->user();

        if ($user->role?->name === 'Franchise Partner') {
            $query->where(function ($movementQuery) use ($user) {
                $movementQuery
                    ->where(function ($q) use ($user) {
                        $q->where('reference_type', StockReceipt::class)
                            ->whereIn('reference_id', StockReceipt::where('franchise_id', $user->franchise_id)->select('id'));
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('reference_type', Sale::class)
                            ->whereIn('reference_id', Sale::where('franchise_id', $user->franchise_id)->select('id'));
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('reference_type', Order::class)
                            ->whereIn('reference_id', Order::where('franchise_id', $user->franchise_id)->select('id'));
                    });
            });
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('franchise_id')) {
            $query->where('reference_type', 'App\Models\Franchise')
                ->where('reference_id', $request->franchise_id);
        }

        $movements = $query->latest()->paginate(50);

        return response()->json($movements);
    }
}
