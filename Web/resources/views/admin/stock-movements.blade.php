@extends('layouts.app')
@section('title', 'Stock Movements')
@section('page-title', 'Stock Movement History')

@push('head')
<style>
    @media print {
        body * { visibility: hidden !important; }
        #printArea, #printArea * { visibility: visible !important; position: absolute; left: 0; top: 0; width: 100%; }
        #printArea { padding: 20px !important; background: #fff !important; }
        .no-print { display: none !important; }
        .sidebar, .topbar, .main-content > *:not(#printArea) { display: none !important; }
        .main-content { margin-left: 0 !important; overflow: visible !important; height: auto !important; }
    }
    .movement-in { color: var(--success); }
    .movement-out { color: var(--danger); }
    .movement-adj { color: var(--warning); }
</style>
@endpush

@section('content')
<div id="printArea">

<div class="print-only mb-6">
    <div style="text-align:center; border-bottom:2px solid #333; padding-bottom:12px; margin-bottom:16px;">
        <h1 style="font-size:20px; font-weight:700; margin:0;">Farmmantra Agro Chemicals Limited</h1>
        <p style="font-size:12px; color:#666; margin:4px 0 0;">Stock Movement History</p>
    </div>
</div>

<div class="card-full mb-6 no-print">
    <div class="card-body py-4 px-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Product</label>
                <select name="product_id" class="w-full rounded-xl border px-3 py-2.5 text-sm" style="background:var(--bg-card); border-color:var(--border-color); color:var(--text-primary)">
                    <option value="">All Products</option>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Type</label>
                <select name="type" class="w-full rounded-xl border px-3 py-2.5 text-sm" style="background:var(--bg-card); border-color:var(--border-color); color:var(--text-primary)">
                    <option value="">All Types</option>
                    <option value="warehouse_out" {{ request('type') === 'warehouse_out' ? 'selected' : '' }}>Warehouse Out</option>
                    <option value="warehouse_in" {{ request('type') === 'warehouse_in' ? 'selected' : '' }}>Warehouse In</option>
                    <option value="franchise_in" {{ request('type') === 'franchise_in' ? 'selected' : '' }}>Franchise In</option>
                    <option value="franchise_out" {{ request('type') === 'franchise_out' ? 'selected' : '' }}>Franchise Out</option>
                    <option value="adjustment" {{ request('type') === 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                </select>
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-xl border px-3 py-2.5 text-sm" style="background:var(--bg-card); border-color:var(--border-color); color:var(--text-primary)">
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-xl border px-3 py-2.5 text-sm" style="background:var(--bg-card); border-color:var(--border-color); color:var(--text-primary)">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition gradient-indigo hover:opacity-90">
                    <i class="fas fa-filter mr-1.5"></i> Filter
                </button>
                <button type="button" onclick="window.print()" class="px-5 py-2.5 rounded-xl text-sm font-semibold transition border" style="border-color:var(--border-color); color:var(--text-primary); background:var(--bg-card)">
                    <i class="fas fa-print mr-1.5"></i> Print
                </button>
                <a href="{{ route('web.admin.reports.export', ['type' => 'stock-movements', 'from' => request('from', now()->startOfMonth()->format('Y-m-d')), 'to' => request('to', now()->format('Y-m-d')), 'product_id' => request('product_id'), 'movement_type' => request('type')]) }}"
                   class="px-5 py-2.5 rounded-xl text-sm font-semibold transition border no-print" style="border-color:var(--border-color); color:var(--text-primary); background:var(--bg-card)">
                    <i class="fas fa-download mr-1.5"></i> CSV
                </a>
            </div>
        </form>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 no-print">
    @php
        $allMovements = $movements->getCollection();
        $totalIn = $allMovements->where('quantity', '>', 0)->sum('quantity');
        $totalOut = abs($allMovements->where('quantity', '<', 0)->sum('quantity'));
    @endphp
    <div class="card-stat text-center">
        <p class="text-2xl font-bold" style="color:var(--text-primary)">{{ number_format($movements->total()) }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">Total Movements</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-2xl font-bold movement-in">+{{ number_format($totalIn) }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">Total In</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-2xl font-bold movement-out">-{{ number_format($totalOut) }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">Total Out</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-2xl font-bold" style="color:var(--text-primary)">{{ number_format($allMovements->count()) }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">On Page</p>
    </div>
</div>

<div class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">
            <i class="fas fa-exchange-alt mr-1.5" style="color:var(--accent)"></i> Stock Movements
        </h3>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark" id="reportTable">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Date & Time</th>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-center">Type</th>
                        <th class="px-4 py-3 text-right">Quantity</th>
                        <th class="px-4 py-3 text-right">Unit Price</th>
                        <th class="px-4 py-3 text-right">Total Value</th>
                        <th class="px-4 py-3 text-left">Notes</th>
                        <th class="px-4 py-3 text-left">User</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $i => $m)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ ($movements->currentPage() - 1) * $movements->perPage() + $i + 1 }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $m->created_at->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $m->product?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm font-mono" style="color:var(--text-muted)">{{ $m->product?->sku ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $typeLabels = [
                                    'warehouse_out' => ['Warehouse Out', 'badge-danger', 'fa-arrow-up'],
                                    'warehouse_in' => ['Warehouse In', 'badge-success', 'fa-arrow-down'],
                                    'franchise_in' => ['Franchise In', 'badge-success', 'fa-arrow-down'],
                                    'franchise_out' => ['Franchise Out', 'badge-danger', 'fa-arrow-up'],
                                    'adjustment' => ['Adjustment', 'badge-warning', 'fa-sliders-h'],
                                ];
                                $label = $typeLabels[$m->type] ?? ['Unknown', 'badge-secondary', 'fa-question'];
                            @endphp
                            <span class="badge {{ $label[1] }}">
                                <i class="fas {{ $label[2] }} mr-1"></i>{{ $label[0] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-semibold {{ $m->quantity >= 0 ? 'movement-in' : 'movement-out' }}">
                            {{ $m->quantity >= 0 ? '+' : '' }}{{ number_format($m->quantity) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">UGX {{ number_format($m->unit_price) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($m->total_value) }}</td>
                        <td class="px-4 py-3 text-sm max-w-[200px] truncate" style="color:var(--text-muted)">{{ $m->notes ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $m->user?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-sm" style="color:var(--text-muted)">
                        <i class="fas fa-inbox text-2xl mb-2 block opacity-30"></i> No stock movements found
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="px-4 py-3 overflow-x-auto no-print" style="border-top:1px solid var(--border-color)">
        {{ $movements->withQueryString()->links() }}
    </div>
</div>

</div>

@push('scripts')
<script>
function exportCSV() {
    const table = document.getElementById('reportTable');
    if (!table) return;
    let csv = [];
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        const rowData = [];
        cols.forEach(col => rowData.push('"' + col.innerText.replace(/"/g, '""').trim() + '"'));
        csv.push(rowData.join(','));
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'stock-movements-{{ now()->format("Y-m-d") }}.csv';
    link.click();
}
</script>
@endpush
@endsection
