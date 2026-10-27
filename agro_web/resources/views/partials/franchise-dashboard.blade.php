@php $d = $dashboard ?? []; @endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    @php
        $stats = [
            ['label' => 'Sales This Month', 'value' => 'UGX ' . number_format($d['summary']['total_sales_this_month'] ?? 0), 'gradient' => 'gradient-green', 'icon' => 'fa-shopping-cart'],
            ['label' => 'Sales YTD', 'value' => 'UGX ' . number_format($d['summary']['total_sales_ytd'] ?? 0), 'gradient' => 'gradient-indigo', 'icon' => 'fa-chart-line'],
            ['label' => 'Outstanding Balance', 'value' => 'UGX ' . number_format($d['summary']['outstanding_balance'] ?? 0), 'gradient' => 'gradient-rose', 'icon' => 'fa-money-bill', 'has_progress' => true],
            ['label' => 'Inventory Value', 'value' => 'UGX ' . number_format($d['summary']['total_inventory_value'] ?? 0), 'gradient' => 'gradient-cyan', 'icon' => 'fa-boxes-stacked', 'has_low_stock' => true],
        ];
    @endphp
    @foreach($stats as $stat)
    <div class="card-stat group">
        <div class="flex items-start gap-3">
            <div class="h-11 w-11 rounded-2xl {{ $stat['gradient'] }} flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform">
                <i class="fas {{ $stat['icon'] }} text-white text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-muted)">{{ $stat['label'] }}</p>
                <p class="text-lg sm:text-xl font-extrabold mt-1" style="color:var(--text-primary)">{{ $stat['value'] }}</p>
                @if(($stat['has_progress'] ?? false) && ($d['summary']['credit_used_percentage'] ?? 0) > 0)
                    <div class="mt-2 w-full rounded-full h-1.5" style="background:var(--bg-input)">
                        <div class="h-1.5 rounded-full {{ ($d['summary']['credit_used_percentage'] ?? 0) > 80 ? 'gradient-rose' : 'gradient-indigo' }}" style="width: {{ min($d['summary']['credit_used_percentage'] ?? 0, 100) }}%"></div>
                    </div>
                @endif
                @if(($stat['has_low_stock'] ?? false) && ($d['summary']['low_stock_items'] ?? 0) > 0)
                    <p class="text-xs text-red-500 mt-1.5">{{ $d['summary']['low_stock_items'] }} items low stock</p>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

@include('partials.fm-ui')
<div class="fm-grid mb-8">
    <div class="fm-kpi"><div class="l">Profit this month</div><div class="v" style="color:{{ ($d['summary']['profit_this_month'] ?? 0) < 0 ? '#dc2626' : '#059669' }}">UGX {{ number_format($d['summary']['profit_this_month'] ?? 0) }}</div><div class="fm-muted">Sales minus franchise cost of goods sold</div></div>
    <div class="fm-kpi"><div class="l">Sales made this month</div><div class="v">{{ number_format($d['summary']['sales_count_this_month'] ?? 0) }}</div></div>
    <a class="fm-kpi" href="{{ route('web.franchise.orders') }}" style="text-decoration:none"><div class="l">Pending orders</div><div class="v">{{ $d['summary']['pending_orders'] ?? 0 }}</div></a>
    <a class="fm-kpi" href="{{ route('web.franchise.orders') }}" style="text-decoration:none; {{ ($d['summary']['awaiting_acceptance'] ?? 0) ? 'border-color:#059669' : '' }}"><div class="l">Deliveries to accept</div><div class="v">{{ $d['summary']['awaiting_acceptance'] ?? 0 }}</div></a>
    <a class="fm-kpi" href="{{ route('web.franchise.statement') }}" style="text-decoration:none"><div class="l">Account statement</div><div class="v" style="font-size:1rem"><i class="fas fa-file-invoice-dollar"></i> Credit / Debit</div></a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="card-full">
        <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Sales by Category</h3></div>
        <div class="card-body">
            @if(!empty($d['sales_by_category']) && count($d['sales_by_category']) > 0)
            <div style="position:relative; height:280px;"><canvas id="categoryChart"></canvas></div>
            @else
            <p class="text-sm text-center py-8" style="color:var(--text-muted)">No sales this month</p>
            @endif
        </div>
    </div>
    <div class="card-full">
        <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Sales Targets</h3></div>
        <div class="card-body space-y-4">
            @forelse($d['sales_targets'] ?? [] as $t)
            <div>
                <div class="flex justify-between text-sm mb-1.5">
                    <span class="font-medium" style="color:var(--text-secondary)">{{ $t['category'] }}</span>
                    <span class="font-bold {{ $t['achievement_percentage'] >= 100 ? 'text-emerald-500' : '' }}" style="{{ $t['achievement_percentage'] < 100 ? 'color:var(--text-primary)' : '' }}">{{ $t['achievement_percentage'] }}%</span>
                </div>
                <div class="w-full rounded-full h-2" style="background:var(--bg-input)">
                    <div class="h-2 rounded-full transition-all duration-700 {{ $t['achievement_percentage'] >= 100 ? 'gradient-green' : 'gradient-indigo' }}" style="width: {{ min($t['achievement_percentage'], 100) }}%"></div>
                </div>
                <p class="text-xs mt-1" style="color:var(--text-muted)">UGX {{ number_format($t['actual_amount']) }} / UGX {{ number_format($t['target_amount']) }}</p>
            </div>
            @empty
            <p class="text-sm text-center py-4" style="color:var(--text-muted)">No targets set</p>
            @endforelse
        </div>
    </div>
</div>

<div class="card-full mb-8">
    <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Current Inventory</h3></div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3 text-left">SKU</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Reorder</th><th class="px-4 py-3 text-right">Value</th><th class="px-4 py-3 text-center">Status</th>
                </tr></thead>
                <tbody>
                    @forelse($d['inventory_status'] ?? [] as $i)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $i['product_name'] }}</td>
                        <td class="px-4 py-3 text-xs font-mono" style="color:var(--text-muted)">{{ $i['sku'] }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">{{ number_format($i['quantity']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-muted)">{{ number_format($i['reorder_level']) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">UGX {{ number_format($i['value']) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($i['is_low_stock'])
                                <span class="badge badge-danger">Low Stock</span>
                            @else
                                <span class="badge badge-success">OK</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No inventory items</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if(!empty($d['sales_by_category']) && count($d['sales_by_category']) > 0)
    const isDark = localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    const textColor = isDark ? '#64748b' : '#94a3b8';
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($d['sales_by_category']->pluck('category_name')->toArray()) !!},
            datasets: [{ data: {!! json_encode($d['sales_by_category']->pluck('total_sales')->toArray()) !!}, backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'], borderWidth: 0, hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { color: textColor, padding: 12, boxWidth: 10, font: { size: 11 } } } } }
    });
    @endif
});
</script>
@endpush
