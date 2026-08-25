@extends('layouts.app')
@section('title', 'All Orders')
@section('page-title', 'All Orders')

@section('content')
@include('admin.partials.business-reset')

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold" style="color:var(--text-primary)">{{ $summary['total'] }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Total Orders</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold text-amber-500">{{ $summary['pending'] }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Pending</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold text-emerald-500">{{ $summary['approved'] }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Approved</p>
    </div>
    <div class="card-stat text-center">
        <p class="text-lg sm:text-2xl font-bold text-red-500">{{ $summary['declined'] }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Declined</p>
    </div>
</div>

<div class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Order List</h3>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Order #</th>
                        <th class="px-4 py-3 text-left">Franchise</th>
                        <th class="px-4 py-3 text-left">Items</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $order->order_number }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $order->franchise?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $order->items->count() }} items</td>
                        <td class="px-4 py-3 text-sm font-semibold text-right" style="color:var(--text-primary)">UGX {{ number_format($order->total_amount) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($order->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                            @elseif($order->status === 'approved')
                            <span class="badge badge-success">Approved</span>
                            @elseif($order->status === 'delivered')
                            <span class="badge badge-info">Delivered</span>
                            @else
                            <span class="badge badge-danger">Declined</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs" style="color:var(--text-muted)">{{ $order->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($order->status === 'pending')
                            <div class="flex items-center justify-center gap-1">
                                <form action="{{ route('web.admin.orders.approve', $order->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn-action btn-approve" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form action="{{ route('web.admin.orders.decline', $order->id) }}" method="POST" class="inline" onsubmit="return confirm('Decline order {{ $order->order_number }}?')">
                                    @csrf
                                    <input type="hidden" name="decline_reason" value="Declined by admin">
                                    <button type="submit" class="btn-action btn-decline" title="Decline">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-xs" style="color:var(--text-muted)">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm" style="color:var(--text-muted)">No orders found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">
        {{ $orders->links() }}
    </div>
</div>
@endsection
