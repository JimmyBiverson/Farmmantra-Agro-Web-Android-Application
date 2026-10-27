@extends('layouts.app')
@section('title', 'My Orders')
@section('page-title', 'My Orders')

@php
$products = \App\Models\Product::with('category')->where('is_active', true)->orderBy('name')->get();
@endphp

@section('content')
@include('partials.fm-ui')
<div x-data="{ items: [{product_id: '', quantity: 1}], addItem() { this.items.push({product_id: '', quantity: 1}); }, removeItem(i) { this.items.splice(i, 1); } }">

    <div class="card-full mb-6">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Product Catalog</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                @foreach($products as $p)
                <div class="rounded-xl border p-3 flex flex-col" style="border-color:var(--border-color); background:var(--bg-input)">
                    <div style="width:100%;height:7rem;border-radius:0.5rem;margin-bottom:0.5rem;display:flex;align-items:center;justify-content:center;background:var(--bg-card);overflow:hidden">
                        @if($p->image_url)
                        <img src="{{ $p->image_url }}" alt="{{ $p->name }}" style="height:100%;max-width:100%;object-fit:contain;background:var(--bg-card)" loading="lazy">
                        @else
                        <div class="flex items-center justify-center text-xs" style="color:var(--text-muted)"><i class="fas fa-flask fa-2x"></i></div>
                        @endif
                    </div>
                    <div class="text-xs font-semibold leading-tight mb-0.5" style="color:var(--text-primary)">{{ $p->name }}</div>
                    <div class="text-[10px] mb-1.5" style="color:var(--text-muted)">{{ $p->category?->name }} · {{ $p->unit_of_measure }}</div>
                    <div class="text-xs font-bold mt-auto" style="color:var(--accent)">UGX {{ number_format($p->standard_price) }} <span class="fm-muted" style="font-weight:400">franchise</span></div>
                    <div class="fm-muted">Retail {{ number_format($p->retail_price) }} · Farmer {{ number_format($p->farmer_unit_price) }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card-full mb-6">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Place New Order</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('web.franchise.orders.place') }}" method="POST">
                @csrf

                <template x-for="(item, index) in items" :key="index">
                    <div class="flex gap-3 items-end mb-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium mb-1" style="color:var(--text-secondary)">Product</label>
                            <select :name="'items[' + index + '][product_id]'" x-model="item.product_id" required class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                                <option value="">Select product</option>
                                @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }}) — UGX {{ number_format($p->standard_price) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-28">
                            <label class="block text-xs font-medium mb-1" style="color:var(--text-secondary)">Qty</label>
                            <input type="number" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity" min="0.01" step="0.01" required class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        </div>
                        <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="btn-delete mb-0.5" style="width:2rem;height:2rem"><i class="fas fa-trash-can text-xs"></i></button>
                    </div>
                </template>

                <button type="button" @click="addItem()" class="inline-flex items-center gap-1 text-xs font-medium mb-4" style="color:var(--accent)">
                    <i class="fas fa-plus"></i> Add Item
                </button>

                <div class="mb-4">
                    <label class="fm-label">Payment terms</label>
                    <div class="fm-row">
                        <label class="fm-check"><input type="radio" name="payment_terms" value="credit" checked> Credit (within credit limit — pay later)</label>
                        <label class="fm-check"><input type="radio" name="payment_terms" value="cash"> Cash (pay by mobile money / bank before dispatch)</label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-medium mb-1" style="color:var(--text-secondary)">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)" placeholder="Any special instructions..."></textarea>
                </div>

                <button type="submit" class="gradient-indigo px-6 py-2.5 rounded-lg text-sm font-semibold text-white">
                    <i class="fas fa-paper-plane mr-1"></i> Place Order
                </button>
            </form>
        </div>
    </div>

    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Order History ({{ $orders->total() }})</h3>
        </div>
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="fm-table">
                    <thead>
                        <tr><th>Order</th><th>Items</th><th class="num">Amount</th><th class="num">Paid / Balance</th><th>Status</th><th>Delivery</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $o)
                        <tr>
                            <td><span style="color:var(--accent); font-weight:600">{{ $o->order_number }}</span><div class="fm-muted">{{ $o->created_at->format('d M Y') }} · {{ ucfirst($o->payment_terms ?? 'credit') }}</div></td>
                            <td>{{ $o->items->pluck('product.name')->implode(', ') }}</td>
                            <td class="num" style="color:var(--text-primary); font-weight:600">UGX {{ number_format($o->total_amount) }}</td>
                            <td class="num">{{ number_format($o->amount_paid) }}<div class="fm-muted">bal. {{ number_format($o->balance_due) }}</div></td>
                            <td>
                                @php
                                    $label = match (true) {
                                        $o->status === 'declined' => ['Declined', 'badge-danger'],
                                        $o->status === 'pending' => ['Pending', 'badge-warning'],
                                        $o->delivery_status === 'confirmed' => ['Received', 'badge-success'],
                                        in_array($o->delivery_status, ['out_for_delivery', 'delivered']) => ['Dispatched — accept it', 'badge-info'],
                                        default => ['Approved', 'badge-primary'],
                                    };
                                @endphp
                                <span class="badge {{ $label[1] }}">{{ $label[0] }}</span>
                                @if($o->decline_reason)<div class="fm-muted">{{ $o->decline_reason }}</div>@endif
                            </td>
                            <td class="fm-muted">
                                @if($o->dispatched_at)Dispatched {{ $o->dispatched_at->format('d M') }} by {{ \App\Models\Order::DISPATCH_MEANS[$o->dispatch_means] ?? '' }}{{ $o->dispatch_vehicle ? ' · '.$o->dispatch_vehicle : '' }}
                                @else Expected {{ $o->expected_delivery_date?->format('d M Y') ?? '—' }}@endif
                                @if($o->delivery_signature)<div class="fm-sig">{{ $o->delivery_signature }}</div>@endif
                            </td>
                            <td style="text-align:right">
                                <div class="fm-row" style="justify-content:flex-end">
                                    @if(in_array($o->delivery_status, ['out_for_delivery', 'delivered']) && $o->status === 'approved')
                                    <a href="{{ route('web.franchise.orders.receive', $o) }}" class="fm-btn fm-btn-success fm-btn-sm"><i class="fas fa-box-open"></i> Accept delivery</a>
                                    @endif
                                    <a href="{{ route('web.print', ['type' => 'order', 'id' => $o->id]) }}" target="_blank" class="fm-btn fm-btn-ghost fm-btn-sm" title="Print order"><i class="fas fa-print"></i></a>
                                    @if($o->status !== 'pending' && $o->status !== 'declined')
                                    <a href="{{ route('web.print', ['type' => 'delivery-note', 'id' => $o->id]) }}" target="_blank" class="fm-btn fm-btn-ghost fm-btn-sm" title="Print delivery note"><i class="fas fa-truck"></i></a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="fm-muted" style="text-align:center; padding:1.5rem">No orders yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $orders->links() }}</div>
    </div>

</div>
@endsection
