@extends('print.layout')
@section('doc-title', 'Payment Receipt')
@section('doc-body')
<div class="grid">
    <div><span>Payment number</span>{{ $payment->payment_number }}</div>
    <div><span>Status</span>{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</div>
    <div><span>Franchise</span>{{ $payment->franchise?->name }} ({{ $payment->franchise?->code }})</div>
    <div><span>Submitted</span>{{ $payment->submitted_at?->format('d M Y H:i') }}</div>
    <div><span>Method</span>{{ \App\Services\PaymentService::METHODS[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method)) }} {{ $payment->bank_name }}</div>
    <div><span>Transaction reference</span>{{ $payment->transaction_reference ?: '—' }}</div>
    <div><span>Amount submitted</span>UGX {{ number_format($payment->amount) }}</div>
    <div><span>Amount accepted</span>{{ $payment->verified_amount !== null ? 'UGX '.number_format($payment->verified_amount) : '—' }}</div>
    @if($payment->orders->count())<div><span>For orders</span>{{ $payment->orders->pluck('order_number')->implode(', ') }}</div>@endif
    @if($payment->verifier)<div><span>Verified by</span>{{ $payment->verifier->name }} {{ $payment->verified_at?->format('d M Y H:i') }}</div>@endif
    @if($payment->acceptor)<div><span>Accepted by</span>{{ $payment->acceptor->name }} {{ $payment->accepted_at?->format('d M Y H:i') }}</div>@endif
    @if($payment->rejection_reason)<div><span>Rejection reason</span>{{ $payment->rejection_reason }}</div>@endif
</div>
@if($payment->acceptance_signature)
<div class="sig">Acceptance signature: <code>{{ $payment->acceptance_signature }}</code></div>
@endif
@endsection
