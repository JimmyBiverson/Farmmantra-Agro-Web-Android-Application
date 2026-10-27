@php $d = $dashboard ?? []; @endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    @php
        $stats = [
            ['label' => 'Pending Orders', 'value' => $d['summary']['pending_orders'] ?? 0, 'gradient' => 'gradient-amber', 'icon' => 'fa-clock'],
            ['label' => 'Overdue Dispatch', 'value' => $d['summary']['overdue_dispatch_orders'] ?? 0, 'gradient' => 'gradient-rose', 'icon' => 'fa-hourglass-end', 'sub' => 'Past '.\App\Models\Order::dispatchTargetDays().'-day target'],
            ['label' => 'Low Stock Items', 'value' => $d['summary']['low_stock_products'] ?? 0, 'gradient' => 'gradient-rose', 'icon' => 'fa-exclamation-triangle'],
            ['label' => 'Warehouse Value', 'value' => 'UGX ' . number_format($d['summary']['total_warehouse_value'] ?? 0), 'gradient' => 'gradient-indigo', 'icon' => 'fa-warehouse', 'sub' => ($d['summary']['active_franchises'] ?? 0) . ' active franchises'],
        ];
    @endphp
    @foreach($stats as $stat)
    <div class="card-stat group">
        <div class="flex items-start gap-3">
            <div class="h-11 w-11 rounded-2xl {{ $stat['gradient'] }} flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform">
                <i class="fas {{ $stat['icon'] }} text-white text-sm"></i>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-muted)">{{ $stat['label'] }}</p>
                <p class="text-lg sm:text-xl font-extrabold mt-1 {{ str_starts_with($stat['value'], 'U') ? '' : 'text-emerald-500' }}" style="{{ str_starts_with($stat['value'], 'U') ? 'color:var(--text-primary)' : '' }}">{{ $stat['value'] }}</p>
                @if($stat['sub'] ?? null)
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">{{ $stat['sub'] }}</p>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-bold" style="color:var(--text-primary)">Pending Orders</h3>
            <span class="badge badge-warning">{{ ($d['pending_orders_list'] ?? collect())->count() }}</span>
        </div>
        <div class="card-body p-0">
            <table class="w-full table-dark">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">Order #</th><th class="px-4 py-3 text-left">Franchise</th><th class="px-4 py-3 text-right">Amount</th>
                </tr></thead>
                <tbody>
                    @forelse($d['pending_orders_list'] ?? [] as $o)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-semibold" style="color:var(--accent)">{{ $o->order_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $o->franchise?->name }}</td>
                        <td class="px-4 py-3 text-sm font-bold text-right" style="color:var(--text-primary)">UGX {{ number_format($o->total_amount) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No pending orders</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-full">
        <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Order Status</h3></div>
        <div class="card-body">
            <div style="position:relative; height:280px;"><canvas id="staffOrderStatusChart"></canvas></div>
        </div>
    </div>
</div>

<div class="card-full mb-8">
    <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Warehouse Stock</h3></div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3 text-left">SKU</th><th class="px-4 py-3 text-right">Quantity</th><th class="px-4 py-3 text-right">Reorder Level</th><th class="px-4 py-3 text-center">Status</th>
                </tr></thead>
                <tbody>
                    @forelse($d['warehouse_stock'] ?? [] as $ws)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $ws->product?->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-xs font-mono" style="color:var(--text-muted)">{{ $ws->product?->sku }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">{{ number_format($ws->quantity) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-muted)">{{ number_format($ws->reorder_level) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($ws->quantity <= $ws->reorder_level)
                                <span class="badge badge-danger">Low Stock</span>
                            @else
                                <span class="badge badge-success">OK</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No warehouse stock data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if(!empty($d['orders_by_status']) && count($d['orders_by_status']) > 0)
    const isDark = localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    const textColor = isDark ? '#64748b' : '#94a3b8';
    new Chart(document.getElementById('staffOrderStatusChart'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($d['orders_by_status']->pluck('status')->toArray()) !!},
            datasets: [{ data: {!! json_encode($d['orders_by_status']->pluck('count')->toArray()) !!}, backgroundColor: ['#f59e0b','#6366f1','#ef4444','#10b981'], borderWidth: 0, hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { color: textColor, padding: 12, boxWidth: 10, font: { size: 11 } } } } }
    });
    @endif
});
</script>
@endpush
