<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockCheckController extends Controller
{
    public function outlets(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        return response()->json(['data' => Franchise::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'recent' => DB::table('stock_checks')->join('franchises', 'franchises.id', '=', 'stock_checks.franchise_id')->orderByDesc('counted_at')->limit(30)->selectRaw('stock_checks.id, franchises.name, counted_at, lines, CASE WHEN photo IS NULL THEN 0 ELSE 1 END as has_photo')->get()]);
    }

    public function photo(Request $request, int $check): BinaryFileResponse
    {
        $this->authorizeManager($request);
        $file = DB::table('stock_checks')->where('id', $check)->value('photo');
        abort_unless($file && Storage::disk('local')->exists($file), 404);

        return response()->file(Storage::disk('local')->path($file), ['Cache-Control' => 'private, no-store']);
    }

    public function inventory(Request $request, Franchise $franchise): JsonResponse
    {
        $this->authorizeManager($request);
        abort_unless($franchise->is_active, 404);
        $items = FranchiseInventory::where('franchise_id', $franchise->id)->join('products', 'products.id', '=', 'franchise_inventories.product_id')
            ->orderBy('products.sku')->get(['product_id', 'sku', 'products.name', 'quantity']);
        $snapshot = ['user' => $request->user()->id, 'franchise' => $franchise->id, 'issued' => now()->timestamp, 'items' => $items->pluck('quantity', 'product_id')->all(), 'id' => (string) Str::uuid()];

        return response()->json(['data' => $items, 'snapshot' => Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate(['snapshot' => 'required|string', 'counted_at' => 'required|date|before_or_equal:now', 'lines' => 'required|array|min:1', 'lines.*.product_id' => 'required|integer|distinct', 'lines.*.count' => 'required|integer|min:0|max:99999999', 'lines.*.remark' => 'nullable|string|max:1000', 'photo' => 'nullable|image|max:4096', 'latitude' => 'required_with:photo|numeric|between:-90,90', 'longitude' => 'required_with:photo|numeric|between:-180,180', 'captured_at' => 'required_with:photo|date|before_or_equal:now']);
        try {
            $snapshot = json_decode(Crypt::decryptString($data['snapshot']), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException) {
            abort(422, 'Invalid inventory snapshot.');
        }
        abort_unless($snapshot['user'] === $request->user()->id, 403);
        $counted = Carbon::parse($data['counted_at']);
        abort_if($counted->timestamp < $snapshot['issued'] - 120, 422, 'Count predates the inventory snapshot.');
        abort_if(count($data['lines']) !== count($snapshot['items']), 422, 'Count each SKU.');
        $lines = [];
        foreach ($data['lines'] as $line) {
            $id = $line['product_id'];
            abort_unless(array_key_exists($id, $snapshot['items']), 422, 'Unknown SKU.');
            $expected = (float) $snapshot['items'][$id];
            $difference = $line['count'] - $expected;
            if ($difference != 0 && blank($line['remark'] ?? null)) {
                throw ValidationException::withMessages(['lines' => 'Explain every stock difference.']);
            }
            $lines[] = [...$line, 'system' => $expected, 'difference' => $difference];
        }
        DB::transaction(function () use ($request, $snapshot, $lines, $counted) {
            Franchise::whereKey($snapshot['franchise'])->lockForUpdate()->firstOrFail();
            if (DB::table('stock_checks')->where('submission_id', $snapshot['id'])->exists()) {
                return;
            }
            $proof = $request->file('photo')?->storeAs('stock-checks', $snapshot['id'].'.jpg', 'local');
            DB::table('stock_checks')->insert(['submission_id' => $snapshot['id'], 'franchise_id' => $snapshot['franchise'], 'user_id' => $request->user()->id, 'counted_at' => $counted, 'lines' => json_encode($lines), 'photo' => $proof, 'photo_location' => $proof ? json_encode($request->only('latitude', 'longitude', 'captured_at')) : null, 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['saved' => true]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->role?->name === 'Operations Manager' || $request->user()->isAdmin(), 403);
    }
}
