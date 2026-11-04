@extends('layouts.app')
@section('title', 'My Inventory')
@section('page-title', 'Franchise Inventory')

@section('content')
@php
    $totalProducts = $inventory->count();
    $lowStock = $inventory->filter(fn($i) => $i->quantity <= $i->reorder_level)->count();
    $totalValue = $inventory->sum('total_value');
    $outOfStock = $inventory->filter(fn($i) => $i->quantity == 0)->count();
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card-full">
        <div class="card-body flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:var(--accent);opacity:0.15">
                <svg class="w-6 h-6" style="color:var(--accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider" style="color:var(--text-muted)">Total Products</p>
                <p class="text-2xl font-bold" style="color:var(--text-primary)">{{ $totalProducts }}</p>
            </div>
        </div>
    </div>

    <div class="card-full">
        <div class="card-body flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#f59e0b;opacity:0.15">
                <svg class="w-6 h-6" style="color:#f59e0b" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider" style="color:var(--text-muted)">Low Stock</p>
                <p class="text-2xl font-bold" style="color:var(--text-primary)">{{ $lowStock }}</p>
            </div>
        </div>
    </div>

    <div class="card-full">
        <div class="card-body flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#10b981;opacity:0.15">
                <svg class="w-6 h-6" style="color:#10b981" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider" style="color:var(--text-muted)">Total Value</p>
                <p class="text-2xl font-bold" style="color:var(--text-primary)">UGX {{ number_format($totalValue) }}</p>
            </div>
        </div>
    </div>

    <div class="card-full">
        <div class="card-body flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#ef4444;opacity:0.15">
                <svg class="w-6 h-6" style="color:#ef4444" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider" style="color:var(--text-muted)">Out of Stock</p>
                <p class="text-2xl font-bold" style="color:var(--text-primary)">{{ $outOfStock }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card-full mb-6" x-data="{ search: '', page: 1, perPage: 15 }">
    <div class="card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Inventory Items ({{ $totalProducts }})</h3>
        <div class="flex items-center gap-3">
            <div class="relative">
                <input type="text" x-model="search" placeholder="Search SKU or product..."
                    class="pl-9 pr-4 py-2 text-sm rounded-lg border focus:outline-none focus:ring-1"
                    style="background:var(--bg-secondary);border-color:var(--border-color);color:var(--text-primary);--tw-ring-color:var(--accent)">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--text-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-left">Unit</th>
                        <th class="px-4 py-3 text-right">Quantity</th>
                        <th class="px-4 py-3 text-right">Reorder Level</th>
                        <th class="px-4 py-3 text-right">Unit Price</th>
                        <th class="px-4 py-3 text-right">Value</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Movements</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($inventory as $item)
                    <tr class="border-b hover:opacity-80 transition-opacity" style="border-color:var(--border-color)"
                        x-show="(search === '' || '{{ strtolower($item->product?->sku . ' ' . $item->product?->name) }}'.includes(search.toLowerCase())) && ({{ $loop->iteration }} >= (page - 1) * perPage + 1 && {{ $loop->iteration }} <= page * perPage)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--accent)">{{ $item->product?->sku }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $item->product?->name }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $item->product?->unit_of_measure ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium" style="color:var(--text-primary)">{{ number_format($item->quantity) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-muted)">{{ number_format($item->reorder_level) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($item->product?->selling_price ?? 0) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium" style="color:var(--text-primary)">UGX {{ number_format($item->total_value) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $item->quantity == 0 ? 'badge-danger' : ($item->quantity <= $item->reorder_level ? 'badge-danger' : 'badge-success') }}">
                                {{ $item->quantity == 0 ? 'Out of Stock' : ($item->quantity <= $item->reorder_level ? 'Low Stock' : 'OK') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="#movements" class="text-xs font-medium underline" style="color:var(--accent)">
                                View
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($totalProducts > 15)
        <div class="px-4 py-3 flex items-center justify-between border-t" style="border-color:var(--border-color)">
            <p class="text-xs" style="color:var(--text-muted)">
                Showing <span x-text="Math.min((page - 1) * perPage + 1, {{ $totalProducts }})"></span> to <span x-text="Math.min(page * perPage, {{ $totalProducts }})"></span> of {{ $totalProducts }}
            </p>
            <div class="flex gap-2">
                <button @click="page = Math.max(1, page - 1)"
                    class="px-3 py-1 text-xs rounded border"
                    style="border-color:var(--border-color);color:var(--text-muted)">Prev</button>
                <button @click="page = Math.min(Math.ceil({{ $totalProducts }} / perPage), page + 1)"
                    class="px-3 py-1 text-xs rounded border"
                    style="border-color:var(--border-color);color:var(--text-muted)">Next</button>
            </div>
        </div>
        @endif
    </div>
</div>

<div id="movements" class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Recent Stock Movements</h3>
    </div>
    <div class="card-body p-0">
        @php
            $movements = \App\Models\StockMovement::where('reference_type', \App\Models\Sale::class)
                ->whereIn('reference_id', \App\Models\Sale::where('franchise_id', auth()->user()->franchise_id)->pluck('id'))
                ->with('product:id,name,sku')
                ->latest()
                ->limit(20)
                ->get();
        @endphp
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-center">Type</th>
                        <th class="px-4 py-3 text-right">Quantity</th>
                        <th class="px-4 py-3 text-right">Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $m)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $m->created_at->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--accent)">{{ $m->product?->sku }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $m->product?->name }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $typeLabels = [
                                    'warehouse_out' => ['Warehouse Out', 'badge-danger'],
                                    'warehouse_in' => ['Warehouse In', 'badge-success'],
                                    'franchise_in' => ['Franchise In', 'badge-success'],
                                    'franchise_out' => ['Franchise Out', 'badge-danger'],
                                    'adjustment' => ['Adjustment', 'badge-warning'],
                                ];
                                $label = $typeLabels[$m->type] ?? ['Unknown', 'badge-secondary'];
                            @endphp
                            <span class="badge {{ $label[1] }}">{{ $label[0] }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-medium" style="color:var(--text-primary)">{{ number_format($m->quantity) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-muted)">{{ $m->reference_type ? class_basename($m->reference_type) . '#' . $m->reference_id : '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No recent stock movements.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
