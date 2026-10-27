@extends('print.layout')
@section('doc-title', 'Sales Receipt')
@section('doc-body')
<div class="grid">
    <div><span>Sale number</span>{{ $sale->sale_number }}</div>
    <div><span>Date</span>{{ $sale->sale_date?->format('d M Y') }}</div>
    <div><span>Sold by</span>{{ $sale->franchise?->name }}</div>
    <div><span>Customer</span>{{ $sale->customer?->name ?? 'Walk-in customer' }} {{ $sale->customer?->phone }}</div>
    <div><span>Price list</span>{{ $sale->price_tier === 'retailer' ? 'Retail price' : 'Farmer price' }}</div>
    <div><span>Payment</span>{{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }} ({{ $sale->payment_status }})</div>
</div>
<table>
    <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
    <tbody>
    @foreach($sale->items as $item)
    <tr><td>{{ $item->product?->name }}</td><td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td><td class="num">{{ number_format($item->unit_price) }}</td><td class="num">{{ number_format($item->subtotal) }}</td></tr>
    @endforeach
    </tbody>
    <tfoot>
        @if((float) $sale->discount > 0)<tr><td colspan="3">Discount</td><td class="num">-{{ number_format($sale->discount) }}</td></tr>@endif
        <tr><td colspan="3">Total (UGX)</td><td class="num">{{ number_format($sale->final_amount) }}</td></tr>
    </tfoot>
</table>
@if($sale->notes)<p class="muted">Remarks: {{ $sale->notes }}</p>@endif
<p class="muted">Served by {{ $sale->creator?->name }}</p>
@endsection
