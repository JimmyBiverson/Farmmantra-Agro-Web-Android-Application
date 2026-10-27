@php $d = $dashboard ?? []; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    @php
        $stats = [
            ['label' => 'Franchises', 'value' => ($d['summary']['active_franchises'] ?? 0) . '/' . ($d['summary']['total_franchises'] ?? 0), 'sub' => 'Active / Total', 'gradient' => 'gradient-indigo', 'icon' => 'fa-store'],
            ['label' => 'Sales (Month)', 'value' => 'UGX ' . number_format(($d['summary']['total_sales_this_month'] ?? 0) / 1000) . 'k', 'sub' => '', 'gradient' => 'gradient-green', 'icon' => 'fa-chart-line', 'change' => true],
            ['label' => 'Outstanding', 'value' => 'UGX ' . number_format(($d['summary']['total_outstanding'] ?? 0) / 1000) . 'k', 'sub' => 'Receivables', 'gradient' => 'gradient-rose', 'icon' => 'fa-exclamation-triangle'],
            ['label' => 'Pending Orders', 'value' => $d['summary']['pending_orders'] ?? 0, 'sub' => ($d['summary']['overdue_dispatch_orders'] ?? 0).' past dispatch target', 'gradient' => 'gradient-amber', 'icon' => 'fa-clock'],
            ['label' => 'Pending Payments', 'value' => $d['summary']['pending_payments'] ?? 0, 'sub' => 'To verify', 'gradient' => 'gradient-purple', 'icon' => 'fa-receipt'],
            ['label' => 'Low Stock', 'value' => $d['summary']['low_stock_products'] ?? 0, 'sub' => 'Products alert', 'gradient' => 'gradient-rose', 'icon' => 'fa-box-open'],
        ];
    @endphp
    @foreach($stats as $stat)
    <div class="card-stat group">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-muted)">{{ $stat['label'] }}</p>
                <p class="text-xl sm:text-2xl font-extrabold mt-2" style="color:var(--text-primary)">{{ $stat['value'] }}</p>
                @if(isset($stat['change']) && $stat['change'])
                    @php $change = ($d['summary']['total_sales_last_month'] ?? 0) > 0 ? round((($d['summary']['total_sales_this_month'] ?? 0) - ($d['summary']['total_sales_last_month'] ?? 0)) / ($d['summary']['total_sales_last_month'] ?? 1) * 100) : 0; @endphp
                    <p class="text-xs mt-1.5 font-medium {{ $change >= 0 ? 'text-emerald-500' : 'text-red-500' }}">
                        <i class="fas fa-{{ $change >= 0 ? 'arrow-up' : 'arrow-down' }} mr-0.5"></i> {{ abs($change) }}% vs last month
                    </p>
                @elseif($stat['sub'])
                    <p class="text-xs mt-1.5" style="color:var(--text-muted)">{{ $stat['sub'] }}</p>
                @endif
            </div>
            <div class="h-11 w-11 rounded-2xl {{ $stat['gradient'] }} flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                <i class="fas {{ $stat['icon'] }} text-white text-sm"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="card-full lg:col-span-2">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-bold" style="color:var(--text-primary)">Sales Trend</h3>
                <p class="text-xs" style="color:var(--text-muted)">Last 30 days performance</p>
            </div>
            <span class="badge badge-success"><i class="fas fa-circle text-[6px] mr-1 animate-pulse"></i>Live</span>
        </div>
        <div class="card-body">
            @if(!empty($d['sales_trend']) && count($d['sales_trend']) > 0)
            <div style="position:relative; height:320px;"><canvas id="salesTrendChart"></canvas></div>
            @else
            <p class="text-center py-8 text-sm" style="color:var(--text-muted)">No sales data yet</p>
            @endif
        </div>
    </div>
    <div class="card-full">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-bold" style="color:var(--text-primary)">By Franchise</h3>
                <p class="text-xs" style="color:var(--text-muted)">This month distribution</p>
            </div>
        </div>
        <div class="card-body">
            @if(!empty($d['sales_by_franchise']) && count($d['sales_by_franchise']) > 0)
            <div style="position:relative; height:260px;"><canvas id="franchiseChart"></canvas></div>
            @else
            <p class="text-center py-8 text-sm" style="color:var(--text-muted)">No data</p>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-bold" style="color:var(--text-primary)">Top Products</h3>
            <a href="{{ route('web.admin.reports') }}" class="text-xs font-semibold" style="color:var(--accent)">View All →</a>
        </div>
        <div class="card-body p-0">
            <table class="w-full table-dark">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">#</th><th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Revenue</th>
                </tr></thead>
                <tbody>
                    @forelse($d['top_products'] ?? [] as $i => $tp)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-xs font-semibold" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $tp->product->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">{{ number_format($tp->total_qty) }}</td>
                        <td class="px-4 py-3 text-sm font-bold text-right text-emerald-500">UGX {{ number_format($tp->total_revenue) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No sales data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-bold" style="color:var(--text-primary)">Recent Orders</h3>
            <a href="{{ route('web.admin.orders') }}" class="text-xs font-semibold" style="color:var(--accent)">View All →</a>
        </div>
        <div class="card-body p-0">
            <table class="w-full table-dark">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">Order</th><th class="px-4 py-3 text-left">Franchise</th><th class="px-4 py-3 text-center">Status</th><th class="px-4 py-3 text-right">Amount</th>
                </tr></thead>
                <tbody>
                    @forelse($d['recent_orders'] ?? [] as $o)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-semibold" style="color:var(--accent)">{{ $o->order_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $o->franchise->code ?? '' }}</td>
                        <td class="px-4 py-3 text-center">
                            @php $statusClass = match($o->status) { 'pending' => 'badge-warning', 'approved' => 'badge-info', 'declined' => 'badge-danger', 'delivered' => 'badge-success', default => 'badge-primary' }; @endphp
                            <span class="badge {{ $statusClass }}">{{ ucfirst($o->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm font-bold text-right" style="color:var(--text-primary)">UGX {{ number_format($o->total_amount) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No orders yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="card-full lg:col-span-2">
        <div class="card-header">
            <h3 class="text-sm font-bold" style="color:var(--text-primary)">Franchise Performance</h3>
            <span class="text-xs" style="color:var(--text-muted)">This month</span>
        </div>
        <div class="card-body p-0">
            <table class="w-full table-dark">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">Franchise</th><th class="px-4 py-3 text-center">Orders</th><th class="px-4 py-3 text-right">Order Value</th><th class="px-4 py-3 text-center">Sales</th><th class="px-4 py-3 text-right">Sales Value</th>
                </tr></thead>
                <tbody>
                    @forelse($d['franchise_performance'] ?? [] as $fp)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-semibold" style="color:var(--text-primary)">{{ $fp->name }}</td>
                        <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ $fp->total_orders }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">UGX {{ number_format($fp->total_order_value ?? 0) }}</td>
                        <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ $fp->total_sales_count }}</td>
                        <td class="px-4 py-3 text-sm font-bold text-right text-emerald-500">UGX {{ number_format($fp->total_sales_value ?? 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No franchise data yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-full">
        <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Quick Actions</h3></div>
        <div class="card-body space-y-2">
            @php
                $actions = [
                    ['url' => route('web.admin.franchises'), 'icon' => 'fa-store', 'gradient' => 'gradient-indigo', 'title' => 'Manage Franchises', 'desc' => 'View & edit accounts'],
                    ['url' => route('web.admin.users'), 'icon' => 'fa-users', 'gradient' => 'gradient-green', 'title' => 'Manage Users', 'desc' => 'Create, edit, deactivate'],
                    ['url' => route('web.admin.products'), 'icon' => 'fa-boxes-stacked', 'gradient' => 'gradient-amber', 'title' => 'Products & Pricing', 'desc' => 'SKUs & price slabs'],
                    ['url' => route('web.admin.reports'), 'icon' => 'fa-chart-bar', 'gradient' => 'gradient-cyan', 'title' => 'View Reports', 'desc' => 'Sales, payments, inventory'],
                    ['url' => route('web.admin.settings.general'), 'icon' => 'fa-cog', 'gradient' => 'gradient-purple', 'title' => 'System Settings', 'desc' => 'Configuration & preferences'],
                ];
            @endphp
            @foreach($actions as $a)
            <a href="{{ $a['url'] }}" class="flex items-center gap-3 p-3 rounded-xl transition group/action" onmouseover="this.style.background='var(--bg-input)'" onmouseout="this.style.background=''">
                <div class="h-10 w-10 rounded-xl {{ $a['gradient'] }} flex items-center justify-center flex-shrink-0 group-hover/action:scale-110 transition-transform shadow-md">
                    <i class="fas {{ $a['icon'] }} text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ $a['title'] }}</p>
                    <p class="text-xs" style="color:var(--text-muted)">{{ $a['desc'] }}</p>
                </div>
                <i class="fas fa-chevron-right text-[10px] ml-auto" style="color:var(--text-muted)"></i>
            </a>
            @endforeach
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="card-full">
        <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Order Status Overview</h3></div>
        <div class="card-body">
            @if(!empty($d['order_status_breakdown']) && count($d['order_status_breakdown']) > 0)
            <div style="position:relative; height:240px;"><canvas id="orderStatusChart"></canvas></div>
            @else
            <p class="text-center py-8 text-sm" style="color:var(--text-muted)">No orders data</p>
            @endif
        </div>
    </div>
    <div class="card-full">
        <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Sales Summary</h3></div>
        <div class="card-body">
            <div class="space-y-3">
                @php
                    $rows = [
                        ['icon' => 'fa-calendar', 'gradient' => 'gradient-green', 'label' => 'This Month', 'value' => 'UGX ' . number_format($d['summary']['total_sales_this_month'] ?? 0), 'cls' => 'text-emerald-500'],
                        ['icon' => 'fa-calendar-minus', 'gradient' => 'gradient-indigo', 'label' => 'Last Month', 'value' => 'UGX ' . number_format($d['summary']['total_sales_last_month'] ?? 0), 'cls' => ''],
                        ['icon' => 'fa-calendar-check', 'gradient' => 'gradient-purple', 'label' => 'Year to Date', 'value' => 'UGX ' . number_format($d['summary']['total_sales_ytd'] ?? 0), 'cls' => 'text-indigo-500'],
                        ['icon' => 'fa-hand-holding-dollar', 'gradient' => 'gradient-rose', 'label' => 'Outstanding', 'value' => 'UGX ' . number_format($d['summary']['total_outstanding'] ?? 0), 'cls' => 'text-red-500'],
                    ];
                @endphp
                @foreach($rows as $r)
                <div class="flex items-center justify-between p-3 rounded-xl transition" onmouseover="this.style.background='var(--bg-input)'" onmouseout="this.style.background=''">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl {{ $r['gradient'] }} flex items-center justify-center shadow-sm"><i class="fas {{ $r['icon'] }} text-white text-xs"></i></div>
                        <span class="text-sm font-medium" style="color:var(--text-primary)">{{ $r['label'] }}</span>
                    </div>
                    <span class="text-sm font-bold {{ $r['cls'] }}" style="{{ !$r['cls'] ? 'color:var(--text-primary)' : '' }}">{{ $r['value'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    const gridColor = isDark ? 'rgba(148,163,184,0.08)' : 'rgba(0,0,0,0.05)';
    const textColor = isDark ? '#64748b' : '#94a3b8';
    const chartDefaults = { responsive: true, maintainAspectRatio: false };

    @if(!empty($d['sales_trend']) && count($d['sales_trend']) > 0)
    new Chart(document.getElementById('salesTrendChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($d['sales_trend']->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray()) !!},
            datasets: [{
                label: 'Sales (UGX)', data: {!! json_encode($d['sales_trend']->pluck('total_sales')->toArray()) !!},
                borderColor: '#6366f1', backgroundColor: (ctx) => { const g = ctx.chart.ctx.createLinearGradient(0,0,0,ctx.chart.height); g.addColorStop(0,'rgba(99,102,241,0.12)'); g.addColorStop(1,'rgba(99,102,241,0.01)'); return g; },
                fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: '#6366f1', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2
            }]
        },
        options: { ...chartDefaults, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: textColor, font: { size: 10 }, maxTicksLimit: 8 } }, y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 }, callback: v => 'UGX ' + (v/1000).toFixed(0) + 'k' } } } }
    });
    @endif

    @if(!empty($d['sales_by_franchise']) && count($d['sales_by_franchise']) > 0)
    new Chart(document.getElementById('franchiseChart'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($d['sales_by_franchise']->pluck('franchise.name')->toArray()) !!},
            datasets: [{ data: {!! json_encode($d['sales_by_franchise']->pluck('total_sales')->toArray()) !!}, backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'], borderWidth: 0, hoverOffset: 8 }]
        },
        options: { ...chartDefaults, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { color: textColor, padding: 12, boxWidth: 10, font: { size: 11 } } } } }
    });
    @endif

    @if(!empty($d['order_status_breakdown']) && count($d['order_status_breakdown']) > 0)
    new Chart(document.getElementById('orderStatusChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($d['order_status_breakdown']->pluck('status')->map(fn($s) => ucfirst($s))->toArray()) !!},
            datasets: [{ data: {!! json_encode($d['order_status_breakdown']->pluck('count')->toArray()) !!}, backgroundColor: ['#f59e0b','#6366f1','#ef4444','#10b981'], borderRadius: 8, barThickness: 32 }]
        },
        options: { ...chartDefaults, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: textColor, font: { size: 11 } } }, y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 }, stepSize: 1 } } } }
    });
    @endif
});
</script>
@endpush
