<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\GoodsReturn;
use App\Models\Order;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CreditNoteService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GoodsReturn::with(['order:id,order_number', 'franchise:id,name', 'user:id,name']);
        if ($request->user()->isFranchisePartner()) {
            $query->where('franchise_id', $request->user()->franchise_id);
        } else {
            abort_unless($request->user()->isAdmin() || $request->user()->isSalesHead() || $request->user()->isWarehouse(), 403);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function eligible(Request $request): JsonResponse
    {
        abort_unless($request->user()->isFranchisePartner(), 403);

        return response()->json(['data' => Order::where('franchise_id', $request->user()->franchise_id)->where('delivery_status', 'confirmed')->with(['items.product', 'stockReceipt.items'])->latest()->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isFranchisePartner() && $request->user()->franchise?->is_active, 403);
        $data = $request->validate(['order_id' => 'required|integer|exists:orders,id', 'reason' => 'required|string|max:3000', 'transport' => 'required|string|max:255', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer|distinct|exists:products,id', 'items.*.quantity' => 'required|numeric|gt:0', 'photos' => 'required|array|min:1|max:5', 'photos.*' => 'required|image|max:5120']);
        $return = DB::transaction(function () use ($request, $data) {
            $order = Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
            abort_unless($order->franchise_id === $request->user()->franchise_id, 403);
            if ($order->delivery_status !== 'confirmed') {
                throw ValidationException::withMessages(['order_id' => 'Choose an order whose delivery has been accepted.']);
            }
            $previous = GoodsReturn::where('order_id', $order->id)->whereNotIn('status', ['declined'])->get();
            $lines = [];
            foreach ($data['items'] as $input) {
                $ordered = $order->items()->where('product_id', $input['product_id'])->first();
                $received = $order->stockReceipt?->items()->where('product_id', $input['product_id'])->sum('received_quantity') ?? 0;
                $already = $previous->sum(fn ($record) => collect($record->items)->where('product_id', $input['product_id'])->sum('quantity'));
                if (! $ordered || (float) $input['quantity'] + $already > $received) {
                    throw ValidationException::withMessages(['items' => 'The return quantity exceeds the unreturned quantity received on this order.']);
                }
                $lines[] = ['product_id' => $ordered->product_id, 'product_name' => $ordered->product->name, 'quantity' => (float) $input['quantity'], 'unit_price' => $ordered->unit_price, 'batch_number' => $ordered->product->batch_number, 'expires_at' => $ordered->product->expires_at];
            }
            $photos = array_map(fn ($photo) => $photo->store('return-photos', 'local'), $request->file('photos', []));

            return GoodsReturn::create(['number' => 'RET-'.Str::upper((string) Str::ulid()), 'order_id' => $order->id, 'franchise_id' => $order->franchise_id, 'user_id' => $request->user()->id, 'reason' => $data['reason'], 'transport' => $data['transport'], 'items' => $lines, 'photos' => $photos, 'status' => 'sales_head']);
        });
        $this->notify($return);

        return response()->json(['data' => $return], 201);
    }

    public function review(Request $request, GoodsReturn $return): JsonResponse
    {
        $data = $request->validate(['approve' => 'required|boolean', 'notes' => 'required|string|max:3000']);
        $return = DB::transaction(function () use ($request, $return, $data) {
            $record = GoodsReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $actor = $request->user();
            abort_unless(($record->status === 'sales_head' && $actor->isSalesHead()) || ($record->status === 'admin' && $actor->isAdmin()) || ($record->status === 'warehouse' && $actor->isWarehouse()), 403, 'Only the current reviewer can act.');
            $history = $record->history ?? [];
            $history[] = ['stage' => $record->status, 'user_id' => $actor->id, 'name' => $actor->name, 'approved' => $data['approve'], 'notes' => $data['notes'], 'at' => now()->toIso8601String()];
            $next = $data['approve'] ? match ($record->status) {
                'sales_head' => 'admin', 'admin' => 'warehouse', default => 'received'
            } : 'declined';
            if ($next === 'received') {
                $note = CreditNoteService::recordSalesReturn($record->franchise, $actor, $record->items, $record->reason, $record->order_id, collect($record->items)->pluck('unit_price', 'product_id')->all(), true);
                $record->credit_note_id = $note->id;
            }
            $record->fill(['status' => $next, 'history' => $history])->save();
            ActivityLogger::log('return.reviewed', $record->number.' '.$next, $record, $actor->id, end($history));

            return $record;
        });
        $this->notify($return);

        return response()->json(['data' => $return]);
    }

    public function photo(Request $request, GoodsReturn $return, int $index): mixed
    {
        $actor = $request->user();
        abort_unless($actor->isAdmin() || $actor->isSalesHead() || $actor->isWarehouse() || ($actor->isFranchisePartner() && $actor->franchise_id === $return->franchise_id), 403);
        abort_unless(isset($return->photos[$index]), 404);

        return response()->file(storage_path('app/private/'.$return->photos[$index]), ['Cache-Control' => 'private, no-store']);
    }

    private function notify(GoodsReturn $return): void
    {
        $ids = User::orderWatchers()->pluck('id')->push($return->user_id)->unique()->all();
        NotificationService::send($ids, 'Goods return '.$return->number, 'Current stage: '.$return->status, 'order', GoodsReturn::class, $return->id, 'operations/returns');
    }
}
