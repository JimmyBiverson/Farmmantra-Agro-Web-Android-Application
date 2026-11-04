@extends('layouts.app')
@section('title', 'Franchise Stock Levels')
@section('page-title', 'Franchise Stock Levels')

@section('content')
<div class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">All Franchise Inventory</h3>
        <span class="text-xs" style="color:var(--text-muted)">{{ $stocks->count() }} items</span>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Franchise</th>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Reorder Level</th>
                        <th class="px-4 py-3 text-right">Value</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $stock)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-primary)">{{ $stock->franchise?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $stock->product?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-xs font-mono" style="color:var(--text-muted)">{{ $stock->product?->sku }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium" style="color:var(--text-primary)">{{ $stock->quantity }} {{ $stock->product?->unit_of_measure }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-muted)">{{ $stock->reorder_level }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($stock->quantity * ($stock->product?->selling_price ?? 0)) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($stock->quantity <= $stock->reorder_level)
                            <span class="badge badge-danger">Low Stock</span>
                            @else
                            <span class="badge badge-success">OK</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No franchise inventory records found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
