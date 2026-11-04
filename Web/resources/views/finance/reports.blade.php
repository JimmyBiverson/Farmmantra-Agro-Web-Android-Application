@extends('layouts.app')
@section('title', 'Financial Reports')
@section('page-title', 'Financial Reports & Reconciliation')

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
</style>
@endpush

@section('content')
<div id="printArea">

<div class="print-only mb-6">
    <div style="text-align:center; border-bottom:2px solid #333; padding-bottom:12px; margin-bottom:16px;">
        <h1 style="font-size:20px; font-weight:700; margin:0;">Farmmantra Agro Chemicals Limited</h1>
        <p style="font-size:12px; color:#666; margin:4px 0 0;">Financial Reports & Reconciliation — {{ now()->format('Y') }}</p>
    </div>
</div>

{{-- Summary Stats --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-6">
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold text-emerald-500 break-words">UGX {{ number_format($data['total_collected_month']) }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">Collected This Month</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold break-words" style="color:var(--text-primary)">UGX {{ number_format($data['total_collected_ytd']) }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">Collected Year-to-Date</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold text-red-500 break-words">UGX {{ number_format($data['total_outstanding']) }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">Total Outstanding</p>
    </div>
</div>

{{-- Monthly Collections Chart --}}
<div class="card-full mb-6">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Monthly Collections ({{ now()->year }})</h3>
    </div>
    <div class="card-body">
        @php
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $collectionMap = $data['monthly_collections']->pluck('total', 'month')->toArray();
            $maxVal = max(array_values($collectionMap) ?: [1]);
        @endphp
        <div class="space-y-3">
            @foreach($months as $i => $month)
            @php $val = $collectionMap[$i + 1] ?? 0; @endphp
            <div class="flex items-center gap-3">
                <span class="text-xs w-8" style="color:var(--text-muted)">{{ $month }}</span>
                <div class="flex-1 h-6 rounded-full overflow-hidden" style="background:var(--bg-card)">
                    <div class="h-full rounded-full gradient-indigo transition-all duration-500" style="width: {{ $maxVal > 0 ? ($val / $maxVal) * 100 : 0 }}%"></div>
                </div>
                <span class="text-xs font-medium w-20 sm:w-24 text-right whitespace-nowrap" style="color:var(--text-primary)">UGX {{ number_format($val) }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Monthly Reconciliation Table --}}
<div class="card-full mb-6">
    <div class="card-header">
        <div>
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">
                <i class="fas fa-table mr-1.5" style="color:var(--accent)"></i> Monthly Reconciliation ({{ now()->year }})
            </h3>
            <p class="text-xs mt-0.5" style="color:var(--text-muted)">Payments submitted, verified, and sales per month</p>
        </div>
        <div class="flex gap-2 no-print">
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl text-xs font-semibold transition border" style="border-color:var(--border-color); color:var(--text-primary); background:var(--bg-card)">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark" id="reconciliationTable">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Month</th>
                        <th class="px-4 py-3 text-right">Sales</th>
                        <th class="px-4 py-3 text-right">Submitted</th>
                        <th class="px-4 py-3 text-right">Accepted</th>
                        <th class="px-4 py-3 text-right">Rejected</th>
                        <th class="px-4 py-3 text-right">Pending</th>
                        <th class="px-4 py-3 text-right">Collection Rate</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['monthly_reconciliation'] as $m)
                    @php
                        $collectionRate = $m['sales'] > 0 ? round(($m['accepted'] / $m['sales']) * 100, 1) : 0;
                    @endphp
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $m['month'] }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($m['sales']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">UGX {{ number_format($m['submitted']) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--success)">UGX {{ number_format($m['accepted']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--danger)">UGX {{ number_format($m['rejected']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--warning)">UGX {{ number_format($m['pending']) }}</td>
                        <td class="px-4 py-3 text-sm text-right">
                            <span class="badge {{ $collectionRate >= 80 ? 'badge-success' : ($collectionRate >= 50 ? 'badge-warning' : 'badge-danger') }}">{{ $collectionRate }}%</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--danger)">UGX {{ number_format($m['outstanding']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No data available yet</td></tr>
                    @endforelse
                    @if($data['monthly_reconciliation']->count() > 0)
                    @php
                        $totals = [
                            'sales' => $data['monthly_reconciliation']->sum('sales'),
                            'submitted' => $data['monthly_reconciliation']->sum('submitted'),
                            'accepted' => $data['monthly_reconciliation']->sum('accepted'),
                            'rejected' => $data['monthly_reconciliation']->sum('rejected'),
                            'pending' => $data['monthly_reconciliation']->sum('pending'),
                        ];
                    @endphp
                    <tr class="font-bold" style="background:var(--bg-input)">
                        <td class="px-4 py-3 text-sm" style="color:var(--text-primary)">Total</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">UGX {{ number_format($totals['sales']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">UGX {{ number_format($totals['submitted']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--success)">UGX {{ number_format($totals['accepted']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--danger)">UGX {{ number_format($totals['rejected']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--warning)">UGX {{ number_format($totals['pending']) }}</td>
                        <td class="px-4 py-3 text-sm text-right">
                            @php $rate = $totals['sales'] > 0 ? round(($totals['accepted'] / $totals['sales']) * 100, 1) : 0; @endphp
                            <span class="badge {{ $rate >= 80 ? 'badge-success' : ($rate >= 50 ? 'badge-warning' : 'badge-danger') }}">{{ $rate }}%</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--danger)">UGX {{ number_format($data['total_outstanding']) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Franchise Reconciliation --}}
<div class="card-full mb-6">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">
            <i class="fas fa-store mr-1.5" style="color:var(--accent)"></i> Franchise Reconciliation ({{ now()->year }})
        </h3>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Franchise</th>
                        <th class="px-4 py-3 text-right">Sales</th>
                        <th class="px-4 py-3 text-right">Total Submitted</th>
                        <th class="px-4 py-3 text-right">Total Accepted</th>
                        <th class="px-4 py-3 text-center">Payments</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                        <th class="px-4 py-3 text-center">Reconciled</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['franchise_reconciliation'] as $f)
                    @php
                        $reconciled = $f->total_sales > 0 && abs($f->total_accepted - $f->total_sales) < ($f->total_sales * 0.05);
                    @endphp
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium" style="color:var(--text-primary)">{{ $f->name }}</p>
                            <p class="text-xs" style="color:var(--text-muted)">{{ $f->code }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">UGX {{ number_format($f->total_sales ?? 0) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">UGX {{ number_format($f->total_submitted ?? 0) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--success)">UGX {{ number_format($f->total_accepted ?? 0) }}</td>
                        <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ $f->total_payments }}</td>
                        <td class="px-4 py-3 text-sm text-right {{ $f->account_balance > 0 ? 'font-bold' : '' }}" style="color:{{ $f->account_balance > 0 ? 'var(--danger)' : 'var(--text-primary)' }}">
                            UGX {{ number_format($f->account_balance) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($reconciled)
                            <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Yes</span>
                            @elseif($f->account_balance == 0)
                            <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Cleared</span>
                            @else
                            <span class="badge badge-warning"><i class="fas fa-clock mr-1"></i>Partial</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No franchise data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
@endsection
