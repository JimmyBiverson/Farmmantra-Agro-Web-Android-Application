@php $d = $dashboard ?? []; @endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    @php
        $stats = [
            ['label' => 'Pending Payments', 'value' => $d['summary']['pending_payments_count'] ?? 0, 'gradient' => 'gradient-amber', 'icon' => 'fa-clock', 'sub' => 'UGX ' . number_format($d['summary']['pending_payments_total'] ?? 0)],
            ['label' => 'Accepted This Month', 'value' => $d['summary']['accepted_this_month'] ?? 0, 'gradient' => 'gradient-green', 'icon' => 'fa-check-double', 'sub' => 'UGX ' . number_format($d['summary']['accepted_amount_this_month'] ?? 0)],
            ['label' => 'Total Outstanding', 'value' => 'UGX ' . number_format($d['summary']['total_outstanding'] ?? 0), 'gradient' => 'gradient-rose', 'icon' => 'fa-exclamation-circle'],
            ['label' => 'Collected YTD', 'value' => 'UGX ' . number_format($d['summary']['total_collected_ytd'] ?? 0), 'gradient' => 'gradient-indigo', 'icon' => 'fa-coins'],
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
                <p class="text-lg sm:text-xl font-extrabold mt-1" style="color:var(--text-primary)">{{ $stat['value'] }}</p>
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
        <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Outstanding by Franchise</h3></div>
        <div class="card-body p-0">
            <table class="w-full table-dark">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">Franchise</th><th class="px-4 py-3 text-right">Balance</th><th class="px-4 py-3 text-right">Credit Limit</th><th class="px-4 py-3 text-right">Used %</th>
                </tr></thead>
                <tbody>
                    @forelse($d['outstanding_by_franchise'] ?? [] as $f)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-semibold" style="color:var(--text-primary)">{{ $f['name'] }}</td>
                        <td class="px-4 py-3 text-sm text-red-500 font-bold text-right">UGX {{ number_format($f['balance']) }}</td>
                        <td class="px-4 py-3 text-sm text-right" style="color:var(--text-secondary)">UGX {{ number_format($f['credit_limit']) }}</td>
                        <td class="px-4 py-3 text-sm text-right">
                            <span class="font-bold {{ $f['utilization'] > 80 ? 'text-red-500' : '' }}" style="{{ $f['utilization'] <= 80 ? 'color:var(--text-primary)' : '' }}">{{ $f['utilization'] }}%</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No outstanding balances</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-full">
        <div class="card-header"><h3 class="text-sm font-bold" style="color:var(--text-primary)">Recent Pending Payments</h3></div>
        <div class="card-body p-0">
            <table class="w-full table-dark">
                <thead><tr class="border-b" style="border-color:var(--border-color)">
                    <th class="px-4 py-3 text-left">Payment #</th><th class="px-4 py-3 text-left">Franchise</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3 text-left">Submitted</th>
                </tr></thead>
                <tbody>
                    @forelse($d['recent_pending'] ?? [] as $p)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-semibold" style="color:var(--accent)">{{ $p->payment_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $p->franchise?->name }}</td>
                        <td class="px-4 py-3 text-sm font-bold text-right" style="color:var(--text-primary)">UGX {{ number_format($p->amount) }}</td>
                        <td class="px-4 py-3 text-xs" style="color:var(--text-muted)">{{ $p->submitted_at?->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No pending payments</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
