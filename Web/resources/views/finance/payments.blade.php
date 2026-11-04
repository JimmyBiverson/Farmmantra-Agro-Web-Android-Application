@extends('layouts.app')
@section('title', 'Payments')
@section('page-title', 'Payment Verification')

@section('content')
<div class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Payments ({{ $payments->total() }})</h3>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Payment #</th>
                        <th class="px-4 py-3 text-left">Franchise</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-left">Method</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Submitted</th>
                        <th class="px-4 py-3 text-center">Proof</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $p)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--accent)">{{ $p->payment_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $p->franchise?->name }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-right" style="color:var(--text-primary)">UGX {{ number_format($p->amount) }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ ucfirst(str_replace('_', ' ', $p->payment_method)) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($p->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                            @elseif($p->status === 'accepted')
                            <span class="badge badge-success">Accepted</span>
                            @else
                            <span class="badge badge-danger">{{ ucfirst($p->status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs" style="color:var(--text-muted)">{{ $p->submitted_at?->format('M d, Y H:i') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($p->proof_of_payment_path)
                            <a href="{{ $p->proof_url }}" target="_blank" class="text-sm" style="color:var(--accent)">View</a>
                            @else
                            <span class="text-sm" style="color:var(--text-muted)">N/A</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($p->status === 'pending')
                            <div class="flex items-center justify-center gap-1">
                                <form action="{{ route('web.finance.payments.accept', $p->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn-action btn-approve" title="Accept Payment">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form action="{{ route('web.finance.payments.reject', $p->id) }}" method="POST" class="inline" onsubmit="return confirm('Reject payment {{ $p->payment_number }}?')">
                                    @csrf
                                    <input type="hidden" name="rejection_reason" value="Rejected by finance">
                                    <button type="submit" class="btn-action btn-decline" title="Reject Payment">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-xs" style="color:var(--text-muted)">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No payments found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $payments->links() }}</div>
</div>
@endsection

