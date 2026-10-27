@extends('layouts.app')
@section('title', 'Warehouse Stock')
@section('page-title', 'Warehouse Stock')

@section('content')
@php $products = \App\Models\Product::where('is_active', true)->orderBy('name')->get(); @endphp

<div x-data="{ open: false }">
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Warehouse Inventory ({{ $stock->count() }} products)</h3>
            @if(auth()->user()->canManageOrders())
            <button @click="open = true" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">
                <i class="fas fa-arrow-up mr-1"></i> Update Stock
            </button>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="w-full table-dark">
                    <thead>
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <th class="px-4 py-3 text-left">SKU</th>
                            <th class="px-4 py-3 text-left">Product</th>
                            <th class="px-4 py-3 text-left">Unit</th>
                            <th class="px-4 py-3 text-right">Quantity</th>
                            <th class="px-4 py-3 text-right">Reserved</th>
                            <th class="px-4 py-3 text-right">Available</th>
                            <th class="px-4 py-3 text-right">Reorder Level</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stock as $s)
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <td class="px-4 py-3 text-sm font-medium" style="color:var(--accent)">{{ $s->product?->sku }}</td>
                            <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $s->product?->name }}</td>
                            <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $s->product?->unit_of_measure }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium" style="color:var(--text-primary)">{{ number_format($s->quantity) }}</td>
                            <td class="px-4 py-3 text-sm text-right" style="color:var(--text-muted)">{{ number_format($s->reserved_quantity) }}</td>
                            <td class="px-4 py-3 text-sm text-right" style="color:var(--text-primary)">{{ number_format($s->quantity - $s->reserved_quantity) }}</td>
                            <td class="px-4 py-3 text-sm text-right" style="color:var(--text-muted)">{{ number_format($s->reorder_level) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge {{ $s->quantity <= $s->reorder_level ? 'badge-danger' : 'badge-success' }}">{{ $s->quantity <= $s->reorder_level ? 'Low Stock' : 'OK' }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No warehouse stock found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="open = false">
        <div class="modal-backdrop" @click="open = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Update Warehouse Stock</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Update quantity or reorder level for a product.</p>
                </div>
                <button @click="open = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.ops.inventory.update') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Product *</label>
                    <select name="product_id" required>
                        <option value="">Select product</option>
                        @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Quantity *</label>
                        <input type="number" name="quantity" required min="0" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Reorder Level</label>
                        <input type="number" name="reorder_level" min="0" placeholder="0">
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-color)">
                    <button type="button" @click="open = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
