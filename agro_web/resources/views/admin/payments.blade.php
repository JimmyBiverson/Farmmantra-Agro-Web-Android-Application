@extends('layouts.app')
@section('title', 'Payment Management')
@section('page-title', 'Payment Management')

@section('content')
@include('admin.partials.business-reset')

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold" style="color:var(--text-primary)">{{ $summary['total'] }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Total Payments</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold text-amber-500">{{ $summary['pending'] }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Pending Review</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold text-emerald-500">{{ $summary['accepted'] }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Accepted</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold text-red-500">{{ $summary['rejected'] }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Rejected</p>
    </div>
</div>

<div class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">All Payment Submissions</h3>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Ref #</th>
                        <th class="px-4 py-3 text-left">Franchise</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-left">Method</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Submitted</th>
                        <th class="px-4 py-3 text-left">Verified</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $payment->payment_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $payment->franchise?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-right" style="color:var(--text-primary)">UGX {{ number_format($payment->amount) }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($payment->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                            @elseif($payment->status === 'accepted')
                            <span class="badge badge-success">Accepted</span>
                            @else
                            <span class="badge badge-danger">Rejected</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs" style="color:var(--text-muted)">{{ $payment->submitted_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-xs" style="color:var(--text-muted)">{{ $payment->accepted_at?->format('M d, Y') ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($payment->status === 'pending')
                            <div class="flex items-center justify-center gap-1">
                                <form action="{{ route('web.admin.payments.accept', $payment->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn-action btn-approve" title="Accept">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form action="{{ route('web.admin.payments.reject', $payment->id) }}" method="POST" class="inline" onsubmit="return confirm('Reject payment {{ $payment->payment_number }}?')">
                                    @csrf
                                    <input type="hidden" name="rejection_reason" value="Rejected by admin">
                                    <button type="submit" class="btn-action btn-decline" title="Reject">
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
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No payment submissions found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">
        {{ $payments->links() }}
    </div>
</div>
@endsection
