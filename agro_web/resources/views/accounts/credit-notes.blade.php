@extends('layouts.app')
@section('title', 'Credit Notes & Returns')
@section('page-title', 'Credit Notes & Sales Returns')

@section('content')
@include('partials.fm-ui')
<div class="fm-grid-2 mb-6" x-data="{ type: '{{ old('type', 'credit_note') }}', rows: [{product_id: '', quantity: ''}] }">
    <div class="fm-panel">
        <h3 class="text-sm font-semibold mb-3" style="color:var(--text-primary)">Record a credit</h3>
        <form method="POST" action="{{ route('web.accounts.creditNotes.store') }}" class="fm-stack">
            @csrf
            <div class="fm-row">
                <label class="fm-check"><input type="radio" name="type" value="credit_note" x-model="type"> Credit note (amount)</label>
                <label class="fm-check"><input type="radio" name="type" value="sales_return" x-model="type"> Sales return (goods back to warehouse)</label>
            </div>
            <div><label class="fm-label">Franchise</label>
                <select class="fm-select" name="franchise_id" required>
                    <option value="">Select…</option>
                    @foreach($franchises as $f)<option value="{{ $f->id }}" @selected(old('franchise_id') == $f->id)>{{ $f->name }}</option>@endforeach
                </select>
            </div>
            <div x-show="type === 'credit_note'"><label class="fm-label">Amount (UGX)</label><input class="fm-input" type="number" name="amount" min="1" step="1" value="{{ old('amount') }}" :required="type === 'credit_note'"></div>
            <div x-show="type === 'sales_return'" class="fm-stack">
                <label class="fm-label">Returned products (valued at franchise price)</label>
                <template x-for="(row, i) in rows" :key="i">
                    <div class="fm-row">
                        <select class="fm-select" style="flex:2" :name="'items[' + i + '][product_id]'" x-model="row.product_id">
                            <option value="">Product…</option>
                            @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} — UGX {{ number_format($p->standard_price) }}</option>@endforeach
                        </select>
                        <input class="fm-input" style="flex:1" type="number" min="0.01" step="0.01" placeholder="Qty" :name="'items[' + i + '][quantity]'" x-model="row.quantity">
                        <button type="button" class="fm-btn fm-btn-ghost fm-btn-sm" @click="rows.splice(i, 1)" x-show="rows.length > 1">✕</button>
                    </div>
                </template>
                <button type="button" class="fm-btn fm-btn-ghost fm-btn-sm" @click="rows.push({product_id: '', quantity: ''})"><i class="fas fa-plus"></i> Add product</button>
            </div>
            <div><label class="fm-label">Reason</label><textarea class="fm-textarea" name="reason" required minlength="5" maxlength="1000">{{ old('reason') }}</textarea></div>
            <button class="fm-btn" type="submit"><i class="fas fa-file-invoice"></i> Record & sign</button>
        </form>
    </div>
    <div class="fm-panel fm-muted">
        <p><strong style="color:var(--text-primary)">Credit note</strong> — reduces what the franchise owes by an amount (e.g. price correction, goodwill).</p>
        <p class="mt-2"><strong style="color:var(--text-primary)">Sales return</strong> — goods returned by the franchise to the Farmmantra warehouse. Franchise stock goes down, warehouse stock goes up, and the franchise account is credited at the franchise price.</p>
        <p class="mt-2">Both appear in the <em>Debit</em> column of the franchise account statement and carry a digital signature number of the user who recorded them.</p>
    </div>
</div>

<div class="card-full">
    <div class="card-header"><h3 class="text-sm font-semibold" style="color:var(--text-primary)">History</h3></div>
    <div class="overflow-x-auto">
    <table class="fm-table">
        <thead><tr><th>Number</th><th>Type</th><th>Franchise</th><th class="num">Amount</th><th>Reason</th><th>Recorded by</th><th>Signature</th><th></th></tr></thead>
        <tbody>
        @forelse($notes as $n)
        <tr>
            <td style="color:var(--accent); font-weight:600">{{ $n->note_number }}<div class="fm-muted">{{ $n->created_at->format('d M Y') }}</div></td>
            <td><span class="badge {{ $n->type === 'sales_return' ? 'badge-warning' : 'badge-info' }}">{{ $n->type === 'sales_return' ? 'Sales return' : 'Credit note' }}</span></td>
            <td>{{ $n->franchise?->name }}</td>
            <td class="num">UGX {{ number_format($n->amount) }}</td>
            <td>{{ $n->reason }}
                @if($n->items)<div class="fm-muted">@foreach($n->items as $it){{ $it['product_name'] }} × {{ $it['quantity'] }}@if(! $loop->last), @endif @endforeach</div>@endif
            </td>
            <td>{{ $n->creator?->name }}</td>
            <td class="fm-sig">{{ $n->signature_number }}</td>
            <td><a class="fm-btn fm-btn-ghost fm-btn-sm" target="_blank" href="{{ route('web.print', ['type' => 'credit-note', 'id' => $n->id]) }}"><i class="fas fa-print"></i></a></td>
        </tr>
        @empty
        <tr><td colspan="8" class="fm-muted" style="text-align:center">No credit notes or returns yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="px-4 py-3">{{ $notes->links() }}</div>
</div>
@endsection
