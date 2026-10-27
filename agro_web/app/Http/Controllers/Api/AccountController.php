<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\Setting;
use App\Models\WarehouseInventory;
use App\Services\ActivityLogger;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Terms & conditions, account statements, stock reports and printable
 * document links for the mobile app.
 */
class AccountController extends Controller
{
    public function terms(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'text' => Setting::get('terms_and_conditions', ''),
            'version' => (int) Setting::get('terms_version', 1),
            'accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            'accepted_version' => $user->terms_version,
            'needs_acceptance' => $user->needsToAcceptTerms(),
        ]]);
    }

    public function acceptTerms(Request $request): JsonResponse
    {
        $request->validate(['accept' => 'accepted']);
        $user = $request->user();
        $version = (int) Setting::get('terms_version', 1);
        $user->update(['terms_accepted_at' => now(), 'terms_version' => $version]);
        ActivityLogger::log('terms.accepted', "{$user->name} accepted terms & conditions v{$version}", $user, $user->id);

        return response()->json(['message' => 'Terms and conditions accepted.', 'data' => ['version' => $version]]);
    }

    /** Franchise: own account statement (credit/debit with running balance). */
    public function myStatement(Request $request): JsonResponse
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $franchise = $request->user()->franchise;

        return response()->json(['data' => LedgerService::statement($franchise, $request->from, $request->to)]);
    }

    /** Finance / admin: any franchise statement. */
    public function franchiseStatement(Request $request, Franchise $franchise): JsonResponse
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);

        return response()->json(['data' => LedgerService::statement($franchise, $request->from, $request->to)]);
    }

    /**
     * Stock report. Franchise users always get their own franchise stock.
     * Admin, sales staff and warehouse can request company (warehouse) stock
     * or any franchise's stock.
     */
    public function stockReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = $user->isFranchisePartner() ? 'franchise' : $request->input('scope', 'company');
        $franchiseId = $user->isFranchisePartner() ? $user->franchise_id : $request->input('franchise_id');

        if ($scope === 'franchise') {
            $query = FranchiseInventory::with('product:id,name,sku,unit_of_measure,standard_price,selling_price,farmer_price', 'franchise:id,name,code');
            if ($franchiseId) {
                $query->where('franchise_id', $franchiseId);
            }
            $rows = $query->get()->map(fn ($i) => [
                'franchise' => $i->franchise?->name,
                'product' => $i->product?->name,
                'sku' => $i->product?->sku,
                'unit' => $i->product?->unit_of_measure,
                'quantity' => (float) $i->quantity,
                'reorder_level' => (float) $i->reorder_level,
                'unit_cost' => (float) ($i->product?->standard_price ?? 0),
                'value' => round((float) $i->quantity * (float) ($i->product?->standard_price ?? 0), 2),
                'is_low_stock' => (float) $i->quantity <= (float) $i->reorder_level,
            ])->values();
        } else {
            $rows = WarehouseInventory::with('product:id,name,sku,unit_of_measure,standard_price')->get()->map(fn ($i) => [
                'franchise' => null,
                'product' => $i->product?->name,
                'sku' => $i->product?->sku,
                'unit' => $i->product?->unit_of_measure,
                'quantity' => (float) $i->quantity,
                'reorder_level' => (float) $i->reorder_level,
                'unit_cost' => (float) ($i->product?->standard_price ?? 0),
                'value' => round((float) $i->quantity * (float) ($i->product?->standard_price ?? 0), 2),
                'is_low_stock' => (float) $i->quantity <= (float) $i->reorder_level,
            ])->values();
        }

        return response()->json(['data' => [
            'scope' => $scope,
            'franchise_id' => $franchiseId ? (int) $franchiseId : null,
            'generated_at' => now()->toIso8601String(),
            'total_value' => round($rows->sum('value'), 2),
            'items' => $rows,
            'print_url' => URL::temporarySignedRoute('print.signed', now()->addMinutes(30), [
                'type' => 'stock-report', 'id' => $franchiseId ?: 0, 'user' => $user->id, 'scope' => $scope,
            ]),
        ]]);
    }

    /**
     * A short-lived signed link to a printable document that the app opens
     * in the browser (print or save as PDF). Access is checked here.
     */
    public function printLink(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();
        $allowed = PrintAccess::check($user, $type, $id);
        if (! $allowed) {
            return response()->json(['message' => 'You cannot print this document.'], 403);
        }

        $params = ['type' => $type, 'id' => $id, 'user' => $user->id];
        foreach (['from', 'to', 'scope'] as $extra) {
            if ($request->filled($extra)) {
                $params[$extra] = $request->input($extra);
            }
        }

        return response()->json(['data' => [
            'url' => URL::temporarySignedRoute('print.signed', now()->addMinutes(30), $params),
            'expires_in_minutes' => 30,
        ]]);
    }
}
