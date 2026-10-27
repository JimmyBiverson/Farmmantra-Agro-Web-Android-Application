@extends('print.layout')
@section('doc-title', $deliveryNote ? 'Delivery Note' : 'Purchase Order / Invoice')
@section('doc-body')
<div class="grid">
    <div><span>Order number</span>{{ $order->order_number }}</div>
    <div><span>Date</span>{{ $order->created_at->format('d M Y H:i') }}</div>
    <div><span>Franchise</span>{{ $order->franchise?->name }} ({{ $order->franchise?->code }})</div>
    <div><span>Contact</span>{{ $order->franchise?->contact_person }} {{ $order->franchise?->phone }}</div>
    <div><span>Payment terms</span>{{ ucfirst($order->payment_terms ?? 'credit') }}</div>
    <div><span>Status</span>{{ ucfirst($order->status) }} / {{ str_replace('_', ' ', $order->delivery_status) }}</div>
    @if($order->expected_delivery_date)<div><span>Expected delivery</span>{{ $order->expected_delivery_date->format('d M Y') }}</div>@endif
    @if($order->dispatched_at)<div><span>Dispatched</span>{{ $order->dispatched_at->format('d M Y H:i') }} by {{ \App\Models\Order::DISPATCH_MEANS[$order->dispatch_means] ?? $order->dispatch_means }} {{ $order->dispatch_vehicle }}</div>@endif
    @if($order->dispatch_remarks)<div><span>Dispatch remarks</span>{{ $order->dispatch_remarks }}</div>@endif
</div>
@php $receiptItems = $order->stockReceipt?->items?->keyBy('order_item_id'); @endphp
<table>
    <thead><tr><th>#</th><th>Product</th><th>SKU</th><th class="num">Qty</th>@if($deliveryNote)<th class="num">Received</th><th>Notes</th>@else<th class="num">Unit price</th><th class="num">Amount</th>@endif</tr></thead>
    <tbody>
    @foreach($order->items as $i => $item)
    <tr>
        <td>{{ $i + 1 }}</td><td>{{ $item->product?->name }}</td><td>{{ $item->product?->sku }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float) ($item->adjusted_quantity ?? $item->quantity), 2), '0'), '.') }}</td>
        @if($deliveryNote)
        @php $ri = $receiptItems?->get($item->id); @endphp
        <td class="num">{{ $ri && $order->stockReceipt->status !== 'pending' ? rtrim(rtrim(number_format((float) $ri->received_quantity, 2), '0'), '.') : '' }}</td>
        <td>{{ $ri?->discrepancy_notes }}</td>
        @else
        <td class="num">{{ number_format($item->unit_price) }}</td><td class="num">{{ number_format($item->subtotal) }}</td>
        @endif
    </tr>
    @endforeach
    </tbody>
    @unless($deliveryNote)
    <tfoot>
        @if((float) $order->tax_amount > 0)<tr><td colspan="5">Of which tax</td><td class="num">{{ number_format($order->tax_amount) }}</td></tr>@endif
        <tr><td colspan="5">Total (UGX)</td><td class="num">{{ number_format($order->total_amount) }}</td></tr>
        <tr><td colspan="5">Paid</td><td class="num">{{ number_format($order->amount_paid) }}</td></tr>
        <tr><td colspan="5">Balance due</td><td class="num">{{ number_format($order->balance_due) }}</td></tr>
    </tfoot>
    @endunless
</table>
@if($deliveryNote)
<p class="muted">Received by: ______________________ &nbsp; Signature: ____________ &nbsp; Date: ___________</p>
@endif
@include('print.partials-signatures', ['signatures' => $order->signatures])
@endsection
