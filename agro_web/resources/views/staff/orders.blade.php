@extends('layouts.app')
@section('title', 'Orders')
@section('page-title', $canManage ? 'Warehouse — Orders' : 'Orders (monitoring)')

@section('content')
@include('partials.fm-ui')

<div class="fm-grid mb-6">
    <a href="{{ route('web.staff.orders', ['status' => 'pending']) }}" class="fm-kpi" style="text-decoration:none">
        <div class="l">Pending acceptance</div>
        <div class="v">{{ \App\Models\Order::where('status', 'pending')->count() }}</div>
    </a>
    <a href="{{ route('web.staff.orders', ['status' => 'approved']) }}" class="fm-kpi" style="text-decoration:none">
        <div class="l">Approved, awaiting dispatch</div>
        <div class="v">{{ \App\Models\Order::where('status', 'approved')->whereNull('dispatched_at')->whereNotIn('delivery_status', ['out_for_delivery', 'delivered', 'confirmed'])->count() }}</div>
    </a>
    <a href="{{ route('web.staff.orders', ['overdue' => 1]) }}" class="fm-kpi" style="text-decoration:none; {{ $overdueCount ? 'border-color:#dc2626' : '' }}">
        <div class="l">Past {{ $targetDays }}-day dispatch target</div>
        <div class="v" style="{{ $overdueCount ? 'color:#dc2626' : '' }}">{{ $overdueCount }}</div>
    </a>
    <a href="{{ route('web.reports.stock') }}" class="fm-kpi" style="text-decoration:none">
        <div class="l">Stock reports</div>
        <div class="v" style="font-size:1rem"><i class="fas fa-warehouse"></i> Company & franchise</div>
    </a>
</div>

@unless($canManage)
<div class="fm-panel mb-4 fm-muted"><i class="fas fa-eye"></i> You are viewing orders in monitoring mode. Orders are accepted and dispatched by the Warehouse. Use <a href="{{ route('web.support.tickets') }}" style="color:var(--accent)">support tickets</a> to raise issues.</div>
@endunless

<div class="card-full">
    <div class="card-header">
        <form method="GET" class="fm-row" style="width:100%">
            <select name="status" class="fm-select" style="width:auto">
                <option value="">All statuses</option>
                @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'declined' => 'Declined', 'delivered' => 'Delivered'] as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                @endforeach
            </select>
            <select name="franchise_id" class="fm-select" style="width:auto">
                <option value="">All franchises</option>
                @foreach($franchises as $f)
                <option value="{{ $f->id }}" @selected((string) request('franchise_id') === (string) $f->id)>{{ $f->name }}</option>
                @endforeach
            </select>
            <label class="fm-check" style="font-size:.8rem"><input type="checkbox" name="overdue" value="1" @checked(request('overdue'))> Overdue only</label>
            <button class="fm-btn fm-btn-sm" type="submit"><i class="fas fa-filter"></i> Filter</button>
            <span class="fm-muted" style="margin-left:auto">{{ $orders->total() }} order(s)</span>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="fm-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Franchise</th>
                        <th>Items</th>
                        <th class="num">Amount</th>
                        <th>Terms</th>
                        <th>Status</th>
                        <th>Dispatch due</th>
                        <th>Signatures</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($orders as $o)
                    <tr class="{{ $o->is_dispatch_overdue ? 'fm-overdue' : '' }}">
                        <td>
                            <div style="color:var(--accent); font-weight:600">{{ $o->order_number }}</div>
                            <div class="fm-muted">{{ $o->created_at->format('d M Y H:i') }}</div>
                        </td>
                        <td>{{ $o->franchise?->name }}</td>
                        <td>
                            @foreach($o->items as $it)
                            <div>{{ $it->product?->name }} × {{ rtrim(rtrim(number_format((float) $it->quantity, 2), '0'), '.') }}</div>
                            @endforeach
                        </td>
                        <td class="num" style="color:var(--text-primary); font-weight:600">UGX {{ number_format($o->total_amount) }}</td>
                        <td><span class="badge {{ $o->payment_terms === 'cash' ? 'badge-warning' : 'badge-primary' }}">{{ ucfirst($o->payment_terms ?? 'credit') }}</span></td>
                        <td>
                            @php
                                $label = match (true) {
                                    $o->status === 'declined' => ['Declined', 'badge-danger'],
                                    $o->status === 'pending' => ['Pending', 'badge-warning'],
                                    $o->delivery_status === 'confirmed' => ['Received by franchise', 'badge-success'],
                                    $o->delivery_status === 'delivered' => ['Arrived — awaiting acceptance', 'badge-info'],
                                    $o->delivery_status === 'out_for_delivery' => ['Dispatched', 'badge-info'],
                                    $o->delivery_status === 'ready_for_delivery' => ['Paid — ready to dispatch', 'badge-primary'],
                                    default => ['Approved', 'badge-primary'],
                                };
                            @endphp
                            <span class="badge {{ $label[1] }}">{{ $label[0] }}</span>
                            @if($o->dispatch_means)
                            <div class="fm-muted">By {{ \App\Models\Order::DISPATCH_MEANS[$o->dispatch_means] ?? $o->dispatch_means }}{{ $o->dispatch_vehicle ? ' · '.$o->dispatch_vehicle : '' }}</div>
                            @endif
                        </td>
                        <td>
                            @if($o->dispatched_at)
                                <span class="fm-muted">Dispatched {{ $o->dispatched_at->format('d M') }}</span>
                            @elseif($o->status !== 'declined')
                                <span style="{{ $o->is_dispatch_overdue ? 'color:#dc2626;font-weight:600' : '' }}">{{ $o->dispatch_due_at?->format('d M Y') }}</span>
                                @if($o->is_dispatch_overdue)<div class="fm-muted" style="color:#dc2626">Overdue</div>@endif
                            @endif
                        </td>
                        <td>
                            @if($o->approval_signature)<div class="fm-sig" title="Confirmed by {{ $o->approvedByUser?->name }}">✔ {{ $o->approval_signature }}</div>@endif
                            @if($o->dispatch_signature)<div class="fm-sig" title="Dispatched by {{ $o->dispatchedByUser?->name }}">⇢ {{ $o->dispatch_signature }}</div>@endif
                            @if($o->delivery_signature)<div class="fm-sig" title="Received by franchise">⌂ {{ $o->delivery_signature }}</div>@endif
                        </td>
                        <td style="text-align:right">
                            <div class="fm-row fm-actions" style="justify-content:flex-end">
                                <a href="{{ route('web.print', ['type' => 'order', 'id' => $o->id]) }}" target="_blank" class="fm-btn fm-btn-ghost fm-btn-sm" title="Print"><i class="fas fa-print"></i></a>
                                @if($canManage && $o->status === 'pending')
                                <details>
                                    <summary class="fm-btn fm-btn-success fm-btn-sm"><i class="fas fa-check"></i> Accept</summary>
                                    <form class="fm-pop fm-stack" method="POST" action="{{ route('web.ops.orders.approve', $o) }}">
                                        @csrf
                                        <div><label class="fm-label">Expected delivery date</label><input class="fm-input" type="date" name="expected_delivery_date" required min="{{ now()->addDay()->toDateString() }}" value="{{ now()->addDays(3)->toDateString() }}"></div>
                                        <div><label class="fm-label">Notes (optional)</label><input class="fm-input" name="notes" maxlength="1000"></div>
                                        <p class="fm-muted">Accepting reserves warehouse stock and records your digital signature.</p>
                                        <button class="fm-btn fm-btn-success" type="submit">Confirm acceptance</button>
                                    </form>
                                </details>
                                <details>
                                    <summary class="fm-btn fm-btn-danger fm-btn-sm"><i class="fas fa-times"></i></summary>
                                    <form class="fm-pop fm-stack" method="POST" action="{{ route('web.ops.orders.decline', $o) }}">
                                        @csrf
                                        <div><label class="fm-label">Reason for declining</label><textarea class="fm-textarea" name="decline_reason" required minlength="3" maxlength="500"></textarea></div>
                                        <button class="fm-btn fm-btn-danger" type="submit">Decline order</button>
                                    </form>
                                </details>
                                @elseif($canManage && $o->status === 'approved' && ! in_array($o->delivery_status, ['out_for_delivery', 'delivered', 'confirmed']))
                                <details>
                                    <summary class="fm-btn fm-btn-sm"><i class="fas fa-truck"></i> Dispatch</summary>
                                    <form class="fm-pop fm-stack" method="POST" action="{{ route('web.ops.orders.dispatch', $o) }}">
                                        @csrf
                                        <div><label class="fm-label">Dispatched by</label>
                                            <select class="fm-select" name="dispatch_means" required>
                                                @foreach($dispatchMeans as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div><label class="fm-label">Vehicle / bus company / reg. no.</label><input class="fm-input" name="dispatch_vehicle" maxlength="100"></div>
                                        <div><label class="fm-label">Remarks</label><textarea class="fm-textarea" name="dispatch_remarks" maxlength="1000" style="min-height:4rem"></textarea></div>
                                        @if($o->payment_terms === 'cash' && ! $o->payment_accepted_count && $o->delivery_status !== 'ready_for_delivery')
                                        <p class="fm-err">Cash order: Finance must accept payment before dispatch.</p>
                                        @endif
                                        <button class="fm-btn" type="submit">Confirm dispatch</button>
                                    </form>
                                </details>
                                @elseif($canManage && $o->delivery_status === 'out_for_delivery')
                                <form method="POST" action="{{ route('web.ops.orders.arrived', $o) }}" onsubmit="return confirm('Mark {{ $o->order_number }} as arrived at the franchise?')">
                                    @csrf
                                    <button class="fm-btn fm-btn-ghost fm-btn-sm" type="submit"><i class="fas fa-location-dot"></i> Arrived</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center; padding:2rem" class="fm-muted">No orders found</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $orders->links() }}</div>
</div>
@endsection
