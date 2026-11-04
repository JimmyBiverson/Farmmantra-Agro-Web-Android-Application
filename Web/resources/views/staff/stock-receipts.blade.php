@extends('layouts.app')
@section('title', 'Stock Receipts')
@section('page-title', 'Stock Receipt Management')

@section('content')
<div class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Stock Receipts ({{ $receipts->total() }})</h3>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Receipt #</th>
                        <th class="px-4 py-3 text-left">Franchise</th>
                        <th class="px-4 py-3 text-left">Order</th>
                        <th class="px-4 py-3 text-left">Items</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $receipt)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--accent)">{{ $receipt->receipt_number }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $receipt->franchise?->name }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $receipt->order?->order_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-primary)">
                            @foreach($receipt->items as $item)
                                <div>{{ $item->product?->name }} &times; {{ number_format($item->received_quantity, 2) }}</div>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $receipt->status === 'confirmed' ? 'badge-success' : ($receipt->status === 'discrepancy' ? 'badge-danger' : 'badge-warning') }}">{{ ucfirst($receipt->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $receipt->received_at?->format('d M Y, H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No stock receipts found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $receipts->links() }}</div>
</div>
@endsection
