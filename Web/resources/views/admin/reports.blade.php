@extends('layouts.app')
@section('title', 'Reports')
@section('page-title', 'Reports & Analytics')

@push('head')
<style>
    @media print {
        body * { visibility: hidden !important; }
        #printArea, #printArea * { visibility: visible !important; position: absolute; left: 0; top: 0; width: 100%; }
        #printArea { padding: 20px !important; background: #fff !important; }
        .no-print { display: none !important; }
        .print-only { display: block !important; }
        .sidebar, .topbar, .main-content > *:not(#printArea) { display: none !important; }
        .main-content { margin-left: 0 !important; overflow: visible !important; height: auto !important; }
        #printArea .card-full { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
        #printArea .badge { border: 1px solid #ccc !important; }
    }
    .print-only { display: none; }
    .report-type-card { cursor: pointer; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    .report-type-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
    .report-type-card.selected { border-color: var(--accent) !important; box-shadow: 0 0 0 2px rgba(99,102,241,0.2), var(--shadow-md); }
    .filter-bar { transition: all 0.3s; }
    .stat-card { transition: all 0.3s; }
    .stat-card:hover { transform: translateY(-2px); }
</style>
@endpush

@section('content')
<div id="printArea">

{{-- Print Header --}}
<div class="print-only mb-6">
    <div style="text-align:center; border-bottom:2px solid #333; padding-bottom:12px; margin-bottom:16px;">
        <h1 style="font-size:20px; font-weight:700; margin:0;">Farmmantra Agro Chemicals Limited</h1>
        <p style="font-size:12px; color:#666; margin:4px 0 0;">{{ $reportTitle }} | {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
    </div>
</div>

{{-- Report Type Selector (shown when no type selected) --}}
@if(!$type)
<div class="mb-6">
    <h3 class="text-lg font-bold mb-1" style="color:var(--text-primary)">Select a Report</h3>
    <p class="text-sm" style="color:var(--text-muted)">Choose a report type below, set your filters, and generate</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    <a href="{{ route('web.admin.reports') }}?type=sales&from={{ $from }}&to={{ $to }}" class="report-type-card card-full">
        <div class="p-6">
            <div class="h-12 w-12 rounded-xl gradient-indigo flex items-center justify-center mb-4">
                <i class="fas fa-chart-bar text-white text-lg"></i>
            </div>
            <h3 class="font-bold text-base" style="color:var(--text-primary)">Sales Report</h3>
            <p class="text-sm mt-1.5 leading-relaxed" style="color:var(--text-muted)">Sales by franchise, category, date range with trends and payment status breakdown</p>
        </div>
    </a>

    <a href="{{ route('web.admin.reports') }}?type=orders&from={{ $from }}&to={{ $to }}" class="report-type-card card-full">
        <div class="p-6">
            <div class="h-12 w-12 rounded-xl gradient-amber flex items-center justify-center mb-4">
                <i class="fas fa-clipboard-list text-white text-lg"></i>
            </div>
            <h3 class="font-bold text-base" style="color:var(--text-primary)">Order Report</h3>
            <p class="text-sm mt-1.5 leading-relaxed" style="color:var(--text-muted)">Order fulfillment and processing analytics with approval rates and values</p>
        </div>
    </a>

    <a href="{{ route('web.admin.reports') }}?type=payments&from={{ $from }}&to={{ $to }}" class="report-type-card card-full">
        <div class="p-6">
            <div class="h-12 w-12 rounded-xl gradient-green flex items-center justify-center mb-4">
                <i class="fas fa-money-bill-wave text-white text-lg"></i>
            </div>
            <h3 class="font-bold text-base" style="color:var(--text-primary)">Payment Report</h3>
            <p class="text-sm mt-1.5 leading-relaxed" style="color:var(--text-muted)">Payment verification and reconciliation status with method breakdown</p>
        </div>
    </a>

    <a href="{{ route('web.admin.reports') }}?type=inventory&from={{ $from }}&to={{ $to }}" class="report-type-card card-full">
        <div class="p-6">
            <div class="h-12 w-12 rounded-xl gradient-rose flex items-center justify-center mb-4">
                <i class="fas fa-box-open text-white text-lg"></i>
            </div>
            <h3 class="font-bold text-base" style="color:var(--text-primary)">Inventory Report</h3>
            <p class="text-sm mt-1.5 leading-relaxed" style="color:var(--text-muted)">Warehouse and franchise stock levels, low stock alerts, and stock valuations</p>
        </div>
    </a>

    <a href="{{ route('web.admin.reports') }}?type=franchise&from={{ $from }}&to={{ $to }}" class="report-type-card card-full">
        <div class="p-6">
            <div class="h-12 w-12 rounded-xl gradient-cyan flex items-center justify-center mb-4">
                <i class="fas fa-store text-white text-lg"></i>
            </div>
            <h3 class="font-bold text-base" style="color:var(--text-primary)">Franchise Performance</h3>
            <p class="text-sm mt-1.5 leading-relaxed" style="color:var(--text-muted)">Compare performance across all franchises — orders, sales, revenue, and payments</p>
        </div>
    </a>

    <a href="{{ route('web.admin.reports') }}?type=profit-loss&from={{ $from }}&to={{ $to }}" class="report-type-card card-full">
        <div class="p-6">
            <div class="h-12 w-12 rounded-xl gradient-purple flex items-center justify-center mb-4">
                <i class="fas fa-piggy-bank text-white text-lg"></i>
            </div>
            <h3 class="font-bold text-base" style="color:var(--text-primary)">Profit & Loss</h3>
            <p class="text-sm mt-1.5 leading-relaxed" style="color:var(--text-muted)">Revenue, costs, discounts, net profit, and margin analysis for the period</p>
        </div>
    </a>
</div>

@else

{{-- Filter Bar --}}
<div class="card-full mb-6 no-print">
    <div class="card-body py-4 px-5">
        <form method="GET" action="{{ route('web.admin.reports') }}" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Report Type</label>
                <select name="type" onchange="this.form.submit()" class="w-full rounded-xl border px-3 py-2.5 text-sm font-medium" style="background:var(--bg-card); border-color:var(--border-color); color:var(--text-primary)">
                    <option value="sales" {{ $type === 'sales' ? 'selected' : '' }}>Sales Report</option>
                    <option value="orders" {{ $type === 'orders' ? 'selected' : '' }}>Order Report</option>
                    <option value="payments" {{ $type === 'payments' ? 'selected' : '' }}>Payment Report</option>
                    <option value="inventory" {{ $type === 'inventory' ? 'selected' : '' }}>Inventory Report</option>
                    <option value="franchise" {{ $type === 'franchise' ? 'selected' : '' }}>Franchise Performance</option>
                    <option value="profit-loss" {{ $type === 'profit-loss' ? 'selected' : '' }}>Profit & Loss</option>
                </select>
            </div>
            @if($type !== 'inventory')
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">From Date</label>
                <input type="date" name="from" value="{{ $from }}" class="w-full rounded-xl border px-3 py-2.5 text-sm" style="background:var(--bg-card); border-color:var(--border-color); color:var(--text-primary)">
            </div>
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">To Date</label>
                <input type="date" name="to" value="{{ $to }}" class="w-full rounded-xl border px-3 py-2.5 text-sm" style="background:var(--bg-card); border-color:var(--border-color); color:var(--text-primary)">
            </div>
            @endif
            @if($type !== 'franchise' && $type !== 'inventory')
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Franchise</label>
                <select name="franchise_id" class="w-full rounded-xl border px-3 py-2.5 text-sm" style="background:var(--bg-card); border-color:var(--border-color); color:var(--text-primary)">
                    <option value="">All Franchises</option>
                    @foreach($franchises as $f)
                    <option value="{{ $f->id }}" {{ $franchiseId == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="flex gap-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition gradient-indigo hover:opacity-90">
                    <i class="fas fa-sync-alt mr-1.5"></i> Generate
                </button>
                <button type="button" onclick="window.print()" class="px-5 py-2.5 rounded-xl text-sm font-semibold transition border" style="border-color:var(--border-color); color:var(--text-primary); background:var(--bg-card)">
                    <i class="fas fa-print mr-1.5"></i> Print
                </button>
                <button type="button" onclick="exportCSV()" class="px-5 py-2.5 rounded-xl text-sm font-semibold transition border" style="border-color:var(--border-color); color:var(--text-primary); background:var(--bg-card)">
                    <i class="fas fa-download mr-1.5"></i> CSV
                </button>
                <a href="{{ route('web.admin.reports') }}" class="px-4 py-2.5 rounded-xl text-sm font-semibold transition border" style="border-color:var(--border-color); color:var(--text-muted); background:var(--bg-card)">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Summary Stats --}}
@if(!empty($summary) && count($summary) > 0)
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    @foreach($summary as $s)
    <div class="card-stat stat-card text-center">
        <div class="h-9 w-9 rounded-lg {{ $s['color'] }} flex items-center justify-center mx-auto mb-2">
            <i class="fas {{ $s['icon'] }} text-white text-xs"></i>
        </div>
        <p class="text-xl font-bold" style="color:var(--text-primary)">
            @if($s['format'] === 'currency') UGX {{ number_format($s['value']) }}
            @elseif($s['format'] === 'percent') {{ $s['value'] }}%
            @else {{ number_format($s['value']) }}
            @endif
        </p>
        <p class="text-[10px] font-medium mt-0.5" style="color:var(--text-muted)">{{ $s['label'] }}</p>
    </div>
    @endforeach
</div>
@endif

{{-- Report Results --}}
<div class="card-full">
    <div class="card-header">
        <div>
            <h3 class="text-sm font-bold" style="color:var(--text-primary)">
                <i class="fas fa-chart-bar mr-1.5" style="color:var(--accent)"></i> {{ $reportTitle }}
            </h3>
            <p class="text-xs mt-0.5" style="color:var(--text-muted)">
                {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                @if($franchiseId) | {{ $franchises->firstWhere('id', $franchiseId)?->name }} @endif
            </p>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            @if($type === 'sales')
            <table class="w-full table-dark" id="reportTable">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Sale No.</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Franchise</th>
                    <th class="px-4 py-3 text-left">Customer</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Discount</th>
                    <th class="px-4 py-3 text-right">Final</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Method</th>
                </tr></thead>
                <tbody>
                    @forelse($reportData as $i => $sale)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $sale->sale_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $sale->sale_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $sale->franchise?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                        <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ $sale->items->count() }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($sale->total_amount) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--danger)">-{{ number_format($sale->discount) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">UGX {{ number_format($sale->final_amount) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($sale->payment_status === 'paid')
                            <span class="badge badge-success">Paid</span>
                            @elseif($sale->payment_status === 'partial')
                            <span class="badge badge-warning">Partial</span>
                            @else
                            <span class="badge badge-danger">Unpaid</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ ucfirst($sale->payment_method ?? '—') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="px-4 py-10 text-center text-sm" style="color:var(--text-muted)">
                        <i class="fas fa-inbox text-2xl mb-2 block opacity-30"></i> No sales found for this period
                    </td></tr>
                    @endforelse
                </tbody>
            </table>

            @elseif($type === 'orders')
            <table class="w-full table-dark" id="reportTable">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Order No.</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Franchise</th>
                    <th class="px-4 py-3 text-left">Ordered By</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Approved By</th>
                </tr></thead>
                <tbody>
                    @forelse($reportData as $i => $order)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $order->order_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $order->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $order->franchise?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $order->orderedByUser?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ $order->items->count() }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">UGX {{ number_format($order->total_amount) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($order->status === 'approved')
                            <span class="badge badge-success">Approved</span>
                            @elseif($order->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                            @else
                            <span class="badge badge-danger">Declined</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $order->approvedByUser?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-sm" style="color:var(--text-muted)">
                        <i class="fas fa-inbox text-2xl mb-2 block opacity-30"></i> No orders found for this period
                    </td></tr>
                    @endforelse
                </tbody>
            </table>

            @elseif($type === 'payments')
            <table class="w-full table-dark" id="reportTable">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Payment No.</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Franchise</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-left">Method</th>
                    <th class="px-4 py-3 text-left">Reference</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Verified By</th>
                </tr></thead>
                <tbody>
                    @forelse($reportData as $i => $p)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $p->payment_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $p->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $p->franchise?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">UGX {{ number_format($p->amount) }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ ucfirst($p->payment_method ?? '—') }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $p->transaction_reference ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($p->status === 'accepted')
                            <span class="badge badge-success">Accepted</span>
                            @elseif($p->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                            @else
                            <span class="badge badge-danger">Rejected</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $p->verifier?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-sm" style="color:var(--text-muted)">
                        <i class="fas fa-inbox text-2xl mb-2 block opacity-30"></i> No payments found for this period
                    </td></tr>
                    @endforelse
                </tbody>
            </table>

            @elseif($type === 'inventory')
            <table class="w-full table-dark" id="reportTable">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">SKU</th>
                    <th class="px-4 py-3 text-right">Unit Price</th>
                    <th class="px-4 py-3 text-right">Warehouse Qty</th>
                    <th class="px-4 py-3 text-right">Reorder Level</th>
                    <th class="px-4 py-3 text-right">Franchise Stock</th>
                    <th class="px-4 py-3 text-right">Stock Value</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr></thead>
                <tbody>
                    @forelse($reportData as $i => $product)
                    @php
                        $whQty = $product->warehouseInventory?->quantity ?? 0;
                        $reorder = $product->warehouseInventory?->reorder_level ?? 0;
                        $frStock = $product->franchiseInventories->sum('quantity');
                        $stockVal = $whQty * $product->standard_price;
                    @endphp
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $product->name }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm font-mono" style="color:var(--text-muted)">{{ $product->sku }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($product->standard_price) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">{{ number_format($whQty) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-muted)">{{ number_format($reorder) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">{{ number_format($frStock) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($stockVal) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($whQty == 0)
                            <span class="badge badge-danger">Out of Stock</span>
                            @elseif($whQty <= $reorder)
                            <span class="badge badge-warning">Low Stock</span>
                            @else
                            <span class="badge badge-success">In Stock</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-sm" style="color:var(--text-muted)">
                        <i class="fas fa-inbox text-2xl mb-2 block opacity-30"></i> No products found
                    </td></tr>
                    @endforelse
                </tbody>
            </table>

            @elseif($type === 'franchise')
            <table class="w-full table-dark" id="reportTable">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Franchise</th>
                    <th class="px-4 py-3 text-left">Region</th>
                    <th class="px-4 py-3 text-center">Orders</th>
                    <th class="px-4 py-3 text-center">Sales</th>
                    <th class="px-4 py-3 text-right">Sales Revenue</th>
                    <th class="px-4 py-3 text-right">Orders Value</th>
                    <th class="px-4 py-3 text-center">Payments</th>
                    <th class="px-4 py-3 text-right">Account Balance</th>
                    <th class="px-4 py-3 text-center">Target</th>
                </tr></thead>
                <tbody>
                    @forelse($reportData as $i => $f)
                    @php $achievePct = $f->monthly_target > 0 ? min(100, round($f->sales_revenue / $f->monthly_target * 100, 1)) : 0; @endphp
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $f->name }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $f->region ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ number_format($f->total_orders) }}</td>
                        <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ number_format($f->total_sales) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">UGX {{ number_format($f->sales_revenue ?? 0) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">UGX {{ number_format($f->orders_value ?? 0) }}</td>
                        <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ number_format($f->payments_count) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($f->account_balance) }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center gap-2 justify-center">
                                <div class="w-16 h-1.5 rounded-full" style="background:var(--bg-input)">
                                    <div class="h-full rounded-full {{ $achievePct >= 100 ? 'bg-green-500' : ($achievePct >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" style="width:{{ $achievePct }}%"></div>
                                </div>
                                <span class="text-[10px] font-semibold" style="color:var(--text-muted)">{{ $achievePct }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-sm" style="color:var(--text-muted)">
                        <i class="fas fa-inbox text-2xl mb-2 block opacity-30"></i> No franchise data found
                    </td></tr>
                    @endforelse
                </tbody>
            </table>

            @elseif($type === 'profit-loss')
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Revenue Section --}}
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider pb-2 border-b" style="color:var(--text-muted)">Revenue</h4>
                        @php
                            $grossRevenue = $summary[0]['value'];
                            $discounts = $summary[1]['value'];
                            $netRevenue = $summary[2]['value'];
                        @endphp
                        <div class="flex justify-between text-sm py-1.5"><span style="color:var(--text-secondary)">Gross Revenue</span><span class="font-semibold" style="color:var(--text-primary)">UGX {{ number_format($grossRevenue) }}</span></div>
                        <div class="flex justify-between text-sm py-1.5"><span style="color:var(--text-secondary)">Less: Discounts</span><span class="font-semibold" style="color:var(--danger)">-UGX {{ number_format($discounts) }}</span></div>
                        <div class="flex justify-between text-sm py-2 border-t font-bold" style="border-color:var(--border-color)"><span style="color:var(--text-primary)">Net Revenue</span><span style="color:var(--success)">UGX {{ number_format($netRevenue) }}</span></div>
                    </div>

                    {{-- Costs & Profit Section --}}
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider pb-2 border-b" style="color:var(--text-muted)">Costs & Profit</h4>
                        @php
                            $cogs = $summary[3]['value'];
                            $netProfit = $summary[4]['value'];
                            $margin = $summary[5]['value'];
                        @endphp
                        <div class="flex justify-between text-sm py-1.5"><span style="color:var(--text-secondary)">Cost of Goods Sold</span><span class="font-semibold" style="color:var(--danger)">-UGX {{ number_format($cogs) }}</span></div>
                        <div class="flex justify-between text-sm py-2 border-t font-bold" style="border-color:var(--border-color)">
                            <span style="color:var(--text-primary)">Net Profit / (Loss)</span>
                            <span style="color:{{ $netProfit >= 0 ? 'var(--success)' : 'var(--danger)' }}">UGX {{ number_format($netProfit) }}</span>
                        </div>
                        <div class="flex justify-between text-sm py-1.5">
                            <span style="color:var(--text-secondary)">Profit Margin</span>
                            <span class="badge {{ $margin >= 0 ? 'badge-success' : 'badge-danger' }}">{{ $margin }}%</span>
                        </div>
                    </div>
                </div>

                {{-- Visual Bar --}}
                <div class="mt-8 pt-6 border-t" style="border-color:var(--border-color)">
                    <h4 class="text-xs font-bold uppercase tracking-wider mb-4" style="color:var(--text-muted)">Revenue Breakdown</h4>
                    <div class="space-y-3">
                        @php
                            $maxVal = max($grossRevenue, $cogs, abs($netProfit)) ?: 1;
                        @endphp
                        <div class="flex items-center gap-3">
                            <span class="text-xs w-28 text-right" style="color:var(--text-muted)">Revenue</span>
                            <div class="flex-1 h-6 rounded-lg overflow-hidden" style="background:var(--bg-input)">
                                <div class="h-full rounded-lg gradient-indigo transition-all" style="width:{{ $grossRevenue / $maxVal * 100 }}%"></div>
                            </div>
                            <span class="text-xs font-semibold w-28" style="color:var(--text-primary)">UGX {{ number_format($grossRevenue) }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs w-28 text-right" style="color:var(--text-muted)">Costs</span>
                            <div class="flex-1 h-6 rounded-lg overflow-hidden" style="background:var(--bg-input)">
                                <div class="h-full rounded-lg gradient-rose transition-all" style="width:{{ $cogs / $maxVal * 100 }}%"></div>
                            </div>
                            <span class="text-xs font-semibold w-28" style="color:var(--text-primary)">UGX {{ number_format($cogs) }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs w-28 text-right" style="color:var(--text-muted)">Profit</span>
                            <div class="flex-1 h-6 rounded-lg overflow-hidden" style="background:var(--bg-input)">
                                <div class="h-full rounded-lg {{ $netProfit >= 0 ? 'gradient-green' : 'gradient-rose' }} transition-all" style="width:{{ abs($netProfit) / $maxVal * 100 }}%"></div>
                            </div>
                            <span class="text-xs font-semibold w-28" style="color:{{ $netProfit >= 0 ? 'var(--success)' : 'var(--danger)' }}">UGX {{ number_format($netProfit) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endif
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
    link.download = '{{ $type ?? "report" }}-{{ $from }}-{{ $to }}.csv';
    link.click();
}
</script>
@endpush
@endsection