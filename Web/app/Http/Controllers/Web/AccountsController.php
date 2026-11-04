<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\PrintAccess;
use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Services\ActivityLogger;
use App\Services\CreditNoteService;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class AccountsController extends Controller
{
    use HandlesWebActions;

    // ── Terms & conditions (franchise first login) ──────────────
    public function terms(Request $request)
    {
        return view('terms', [
            'terms' => Setting::get('terms_and_conditions', ''),
            'version' => (int) Setting::get('terms_version', 1),
            'needsAcceptance' => $request->user()->needsToAcceptTerms(),
        ]);
    }

    public function acceptTerms(Request $request)
    {
        $request->validate(['accept' => 'accepted'], ['accept.accepted' => 'Please tick the box to confirm you have read and accept the terms.']);
        $user = $request->user();
        $version = (int) Setting::get('terms_version', 1);
        $user->update(['terms_accepted_at' => now(), 'terms_version' => $version]);
        ActivityLogger::log('terms.accepted', "{$user->name} accepted terms & conditions v{$version}", $user, $user->id);

        return redirect()->route('web.dashboard')->with('success', 'Thank you. Terms and conditions accepted.');
    }

    // ── Account statements ──────────────────────────────────────
    public function myStatement(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $franchise = $request->user()->franchise;
        $statement = LedgerService::statement($franchise, $request->from, $request->to);

        return view('accounts.statement', ['statement' => $statement, 'franchises' => null, 'selected' => $franchise]);
    }

    public function statement(Request $request)
    {
        $request->validate(['franchise_id' => 'nullable|exists:franchises,id', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $franchises = Franchise::orderBy('name')->get(['id', 'name', 'code', 'account_balance', 'credit_limit']);
        $selected = $request->filled('franchise_id') ? Franchise::find($request->franchise_id) : null;
        $statement = $selected ? LedgerService::statement($selected, $request->from, $request->to) : null;

        return view('accounts.statement', compact('statement', 'franchises', 'selected'));
    }

    // ── Credit notes & sales returns (finance + admin) ──────────
    public function creditNotes()
    {
        $notes = CreditNote::with(['franchise:id,name', 'creator:id,name'])->latest()->paginate(20);
        $franchises = Franchise::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $products = Product::orderBy('name')->get(['id', 'name', 'sku', 'standard_price']);

        return view('accounts.credit-notes', compact('notes', 'franchises', 'products'));
    }

    public function storeCreditNote(Request $request)
    {
        $request->validate([
            'type' => 'required|in:credit_note,sales_return',
            'franchise_id' => 'required|exists:franchises,id',
            'order_id' => 'nullable|exists:orders,id',
            'amount' => 'required_if:type,credit_note|nullable|numeric|min:1',
            'items' => 'required_if:type,sales_return|nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'reason' => 'required|string|min:5|max:1000',
        ]);
        $franchise = Franchise::findOrFail($request->franchise_id);

        return $this->attempt(function () use ($request, $franchise) {
            if ($request->type === 'credit_note') {
                return CreditNoteService::issueCreditNote($franchise, $request->user(), (float) $request->amount, $request->reason, $request->order_id);
            }
            $items = collect($request->items)->filter(fn ($i) => ! empty($i['product_id']) && (float) ($i['quantity'] ?? 0) > 0)->values()->all();

            return CreditNoteService::recordSalesReturn($franchise, $request->user(), $items, $request->reason, $request->order_id);
        }, fn ($note) => ($note->type === 'sales_return' ? 'Sales return ' : 'Credit note ').$note->note_number.' recorded for UGX '.number_format((float) $note->amount).'.');
    }

    // ── Stock reports (company + franchise) ─────────────────────
    public function stockReport(Request $request)
    {
        $user = $request->user();
        $data = $this->stockReportData($user, $request->input('scope'), $request->input('franchise_id'));
        $franchises = $user->isFranchisePartner() ? collect() : Franchise::orderBy('name')->get(['id', 'name']);

        return view('accounts.stock-report', $data + ['franchises' => $franchises]);
    }

    private function stockReportData(User $user, ?string $scope, $franchiseId): array
    {
        if ($user->isFranchisePartner()) {
            $scope = 'franchise';
            $franchiseId = $user->franchise_id;
        }
        $scope = $scope === 'franchise' ? 'franchise' : 'company';

        if ($scope === 'franchise') {
            $items = FranchiseInventory::with(['product:id,name,sku,unit_of_measure,standard_price', 'franchise:id,name'])
                ->when($franchiseId, fn ($q) => $q->where('franchise_id', $franchiseId))
                ->orderBy('franchise_id')->get();
        } else {
            $items = WarehouseInventory::with('product:id,name,sku,unit_of_measure,standard_price')->get();
        }

        return [
            'scope' => $scope,
            'franchiseId' => $franchiseId,
            'selectedFranchise' => $franchiseId ? Franchise::find($franchiseId) : null,
            'items' => $items,
            'totalValue' => $items->sum(fn ($i) => (float) $i->quantity * (float) ($i->product?->standard_price ?? 0)),
        ];
    }

    // ── Printable documents ─────────────────────────────────────
    /** Logged-in (session) printing from the web dashboard. */
    public function print(Request $request, string $type, int $id)
    {
        abort_unless(PrintAccess::check($request->user(), $type, $id), 403);

        return $this->renderDocument($request->user(), $type, $id, $request);
    }

    /** Signed link printing for the mobile app (opens in the browser). */
    public function printSigned(Request $request)
    {
        $user = User::findOrFail((int) $request->query('user'));
        $type = (string) $request->query('type');
        $id = (int) $request->query('id');
        abort_unless(PrintAccess::check($user, $type, $id), 403);

        return $this->renderDocument($user, $type, $id, $request);
    }

    private function renderDocument(User $user, string $type, int $id, Request $request)
    {
        $company = [
            'name' => Setting::get('site_name', 'Farmmantra Agro Chemicals Limited'),
            'address' => Setting::get('address', 'Kampala, Uganda'),
            'phone' => Setting::get('contact_phone', ''),
            'email' => Setting::get('contact_email', ''),
        ];
        $printedBy = $user->name.' ('.($user->role?->name ?? 'User').')';

        ActivityLogger::log('document.printed', "Printed {$type} #{$id}", null, $user->id, ['type' => $type, 'id' => $id]);

        return match ($type) {
            'order', 'delivery-note' => view('print.order', [
                'order' => Order::with(['franchise', 'items.product', 'orderedByUser:id,name', 'approvedByUser:id,name', 'dispatchedByUser:id,name', 'deliveryConfirmedByUser:id,name', 'stockReceipt.items.product', 'signatures'])->findOrFail($id),
                'deliveryNote' => $type === 'delivery-note',
            ] + compact('company', 'printedBy')),
            'receipt' => view('print.order', [
                'order' => StockReceipt::findOrFail($id)->order()->with(['franchise', 'items.product', 'orderedByUser:id,name', 'approvedByUser:id,name', 'dispatchedByUser:id,name', 'deliveryConfirmedByUser:id,name', 'stockReceipt.items.product', 'signatures'])->firstOrFail(),
                'deliveryNote' => true,
            ] + compact('company', 'printedBy')),
            'payment' => view('print.payment', ['payment' => PaymentSubmission::with(['franchise', 'verifier:id,name', 'acceptor:id,name', 'orders:id,order_number'])->findOrFail($id)] + compact('company', 'printedBy')),
            'sale' => view('print.sale', ['sale' => Sale::with(['franchise', 'customer', 'items.product', 'creator:id,name'])->findOrFail($id)] + compact('company', 'printedBy')),
            'credit-note' => view('print.credit-note', ['note' => CreditNote::with(['franchise', 'creator:id,name', 'order:id,order_number'])->findOrFail($id)] + compact('company', 'printedBy')),
            'statement' => view('print.statement', ['statement' => LedgerService::statement(Franchise::findOrFail($id), $request->query('from'), $request->query('to'))] + compact('company', 'printedBy')),
            'stock-report' => view('print.stock-report', $this->stockReportData($user, $request->query('scope', $id ? 'franchise' : 'company'), $id ?: null) + compact('company', 'printedBy')),
        };
    }
}
