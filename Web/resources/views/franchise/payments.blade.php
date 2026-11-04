@extends('layouts.app')
@section('title', 'My Payments')
@section('page-title', 'Payments')

@section('content')
@php $franchise = auth()->user()->franchise; @endphp

<div class="mb-8">
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Submit Payment</h3>
        </div>
        <div class="card-body">
            <div class="mb-4 p-3 rounded-lg" style="background:var(--bg-input); display:flex; flex-wrap:wrap; gap:1rem; align-items:center">
                <div>
                    <span class="text-xs" style="color:var(--text-muted)">Outstanding Balance:</span>
                    <span class="text-lg font-bold" style="color:var(--accent)">UGX {{ number_format($franchise->account_balance ?? 0) }}</span>
                </div>
                <span class="text-xs" style="color:var(--text-muted)">You can pay any amount at any time (e.g. daily as your customers pay you). Payments are applied to your oldest orders first.</span>
                <a href="{{ route('web.franchise.statement') }}" class="text-xs font-semibold" style="color:var(--accent); margin-left:auto"><i class="fas fa-file-invoice-dollar"></i> View statement</a>
            </div>

            <form action="{{ route('web.franchise.payments.submit') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Amount (UGX) *</label>
                        <input type="number" name="amount" min="1" required value="{{ old('amount') }}"
                            class="w-full rounded-lg border px-3 py-2.5 text-sm"
                            style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                            placeholder="Enter amount">
                        @error('amount') <p class="text-xs mt-1" style="color:#ef4444">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Payment Method *</label>
                        <select name="payment_method" required
                            class="w-full rounded-lg border px-3 py-2.5 text-sm"
                            style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                            <option value="">Select method</option>
                            <option value="mobile_money" {{ old('payment_method') === 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                            <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        </select>
                        @error('payment_method') <p class="text-xs mt-1" style="color:#ef4444">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Transaction Reference *</label>
                        <input type="text" name="transaction_reference" required maxlength="100" value="{{ old('transaction_reference') }}"
                            class="w-full rounded-lg border px-3 py-2.5 text-sm"
                            style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                            placeholder="e.g. TXN-12345">
                        @error('transaction_reference') <p class="text-xs mt-1" style="color:#ef4444">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Proof of Payment</label>
                        <input type="file" name="proof_of_payment" accept="image/*,application/pdf"
                            class="w-full rounded-lg border px-3 py-2.5 text-sm"
                            style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        <p class="text-xs mt-1" style="color:var(--text-muted)">Image or PDF, max 5MB</p>
                        @error('proof_of_payment') <p class="text-xs mt-1" style="color:#ef4444">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="px-5 py-2.5 gradient-indigo text-white rounded-lg text-sm font-semibold hover:opacity-90 transition">
                        Submit Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Payment History ({{ $payments->total() }})</h3>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Payment #</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-left">Method</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Submitted</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $p)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--accent)">{{ $p->payment_number }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-right" style="color:var(--text-primary)">UGX {{ number_format($p->amount) }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ ucfirst(str_replace('_', ' ', $p->payment_method)) }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $p->transaction_reference ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($p->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                            @elseif($p->status === 'accepted')
                            <span class="badge badge-success">Accepted</span>
                            @elseif($p->status === 'verified')
                            <span class="badge badge-info">Verified</span>
                            @else
                            <span class="badge badge-danger">{{ ucfirst($p->status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs" style="color:var(--text-muted)">{{ $p->submitted_at?->format('M d, Y H:i') }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('web.print', ['type' => 'payment', 'id' => $p->id]) }}" target="_blank" title="Print receipt" style="color:var(--accent)"><i class="fas fa-print"></i></a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No payments submitted yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $payments->links() }}</div>
</div>
@endsection
