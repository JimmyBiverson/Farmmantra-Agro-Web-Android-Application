@extends('layouts.app')
@section('title', 'Accept Delivery')
@section('page-title', 'Accept Delivery — '.$order->order_number)

@section('content')
@include('partials.fm-ui')
@php $receipt = $order->stockReceipt; @endphp

@if(! $receipt || ! in_array($order->delivery_status, ['out_for_delivery', 'delivered']) || $receipt->status !== 'pending')
<div class="fm-panel">
    <p style="color:var(--text-primary)">This order cannot be accepted right now
        @if($receipt && $receipt->status !== 'pending')— it was already accepted on {{ $receipt->received_at?->format('d M Y H:i') }} (signature <span class="fm-sig">{{ $receipt->signature_number }}</span>).
        @else— it has not been dispatched yet.@endif
    </p>
    <a href="{{ route('web.franchise.orders') }}" class="fm-btn fm-btn-ghost mt-3">Back to orders</a>
</div>
@else
<form method="POST" action="{{ route('web.franchise.orders.receive.submit', $order) }}" class="fm-stack">
    @csrf
    <div class="fm-panel">
        <div class="fm-row mb-2">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">1. Count the goods you received</h3>
            <span class="fm-muted" style="margin-left:auto">Dispatched {{ $order->dispatched_at?->format('d M Y') }} by {{ \App\Models\Order::DISPATCH_MEANS[$order->dispatch_means] ?? '—' }}{{ $order->dispatch_vehicle ? ' · '.$order->dispatch_vehicle : '' }}</span>
        </div>
        <div class="overflow-x-auto">
        <table class="fm-table">
            <thead><tr><th>Product</th><th class="num">Dispatched</th><th class="num" style="width:9rem">Received</th><th>Shortage / damage notes</th></tr></thead>
            <tbody>
            @foreach($receipt->items as $i => $item)
            <tr>
                <td>{{ $item->product?->name }}<div class="fm-muted">{{ $item->product?->sku }}</div></td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $item->ordered_quantity, 2), '0'), '.') }}</td>
                <td class="num">
                    <input type="hidden" name="items[{{ $i }}][stock_receipt_item_id]" value="{{ $item->id }}">
                    <input class="fm-input" type="number" step="0.01" min="0" max="{{ $item->ordered_quantity }}" name="items[{{ $i }}][received_quantity]" value="{{ old("items.$i.received_quantity", (float) $item->ordered_quantity) }}" required style="text-align:right">
                </td>
                <td><input class="fm-input" name="items[{{ $i }}][discrepancy_notes]" maxlength="500" placeholder="e.g. 2 cartons damaged" value="{{ old("items.$i.discrepancy_notes") }}"></td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="mt-3"><label class="fm-label">General notes (optional)</label><input class="fm-input" name="notes" maxlength="1000" value="{{ old('notes') }}"></div>
        <p class="fm-muted mt-2">You are charged only for the quantity you receive.</p>
    </div>

    <div class="fm-panel">
        <h3 class="text-sm font-semibold mb-2" style="color:var(--text-primary)">2. Read the terms and conditions</h3>
        <div class="fm-terms" style="max-height:16rem">{{ $terms }}</div>
        <label class="fm-check mt-3"><input type="checkbox" name="accept_terms" value="1" required> I have counted the goods, read the terms and conditions, and I accept this delivery.</label>
        @error('accept_terms')<div class="fm-err">{{ $message }}</div>@enderror
    </div>

    <div class="fm-row">
        <button class="fm-btn fm-btn-success" type="submit"><i class="fas fa-file-signature"></i> Accept delivery & sign</button>
        <a href="{{ route('web.franchise.orders') }}" class="fm-btn fm-btn-ghost">Cancel</a>
        <span class="fm-muted">A digital signature number will be generated in your name.</span>
    </div>
</form>
@endif
@endsection
