@extends('print.layout')
@section('doc-title', $note->type === 'sales_return' ? 'Sales Return Note' : 'Credit Note')
@section('doc-body')
<div class="grid">
    <div><span>Number</span>{{ $note->note_number }}</div>
    <div><span>Date</span>{{ $note->created_at->format('d M Y H:i') }}</div>
    <div><span>Franchise</span>{{ $note->franchise?->name }} ({{ $note->franchise?->code }})</div>
    <div><span>Amount credited</span>UGX {{ number_format($note->amount) }}</div>
    @if($note->order)<div><span>Related order</span>{{ $note->order->order_number }}</div>@endif
    <div><span>Recorded by</span>{{ $note->creator?->name }}</div>
</div>
@if($note->items)
<table>
    <thead><tr><th>Product</th><th class="num">Qty returned</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
    <tbody>@foreach($note->items as $it)<tr><td>{{ $it['product_name'] }}</td><td class="num">{{ $it['quantity'] }}</td><td class="num">{{ number_format($it['unit_price']) }}</td><td class="num">{{ number_format($it['subtotal']) }}</td></tr>@endforeach</tbody>
</table>
@endif
<p><strong>Reason:</strong> {{ $note->reason }}</p>
@if($note->signature_number)<div class="sig">Signature: <code>{{ $note->signature_number }}</code></div>@endif
@endsection
