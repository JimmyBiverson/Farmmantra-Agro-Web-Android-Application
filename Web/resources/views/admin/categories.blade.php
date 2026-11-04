@extends('layouts.app')
@section('title', 'Categories')
@section('page-title', 'Product Categories')

@section('content')
<div x-data="{ editing: false, editCat: {} }">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1">
        <div class="card-full">
            <div class="card-header">
                <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Add Category</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('web.admin.categories.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Category Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Herbicides" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Slug</label>
                        <input type="text" name="slug" placeholder="auto-generated from name" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Description</label>
                        <textarea name="description" rows="3" placeholder="Brief description..." class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                        <i class="fas fa-save mr-1"></i> Save Category
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="card-full">
            <div class="card-header">
                <h3 class="text-sm font-semibold" style="color:var(--text-primary)">All Categories ({{ $categories->count() }})</h3>
            </div>
            <div class="card-body p-0">
                <div class="overflow-x-auto">
                    <table class="w-full table-dark">
                        <thead>
                            <tr class="border-b" style="border-color:var(--border-color)">
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Name</th>
                                <th class="px-4 py-3 text-left">Slug</th>
                                <th class="px-4 py-3 text-center">Products</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $cat)
                            <tr class="border-b" style="border-color:var(--border-color)">
                                <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $cat->sort_order }}</td>
                                <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $cat->name }}</td>
                                <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $cat->slug }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-info">{{ $cat->products_count }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge {{ $cat->is_active ? 'badge-success' : 'badge-danger' }}">{{ $cat->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" class="btn-action" title="Edit" style="color:var(--accent); background:rgba(99,102,241,0.12)"
                                            @click="editCat = {{ \Illuminate\Support\Js::from(['id' => $cat->id, 'name' => $cat->name, 'slug' => $cat->slug, 'description' => (string) $cat->description, 'sort_order' => (string) $cat->sort_order]) }}; editing = true">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form action="{{ route('web.admin.categories.delete') }}" method="POST" class="inline" onsubmit="return confirm('Delete category {{ addslashes($cat->name) }}?')">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $cat->id }}">
                                            <button type="submit" class="btn-delete"><i class="fas fa-trash-can text-xs"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No categories yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div x-show="editing" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="editing = false">
        <div class="modal-backdrop" @click="editing = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold" style="color:var(--text-primary)">Edit Category</h3>
                <button @click="editing = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.categories.update') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="id" :value="editCat.id">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Category Name *</label>
                    <input type="text" name="name" required x-model="editCat.name">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Slug</label>
                    <input type="text" name="slug" x-model="editCat.slug" placeholder="auto-generated from name">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Description</label>
                    <textarea name="description" rows="3" x-model="editCat.description" placeholder="Brief description..."></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Sort Order</label>
                    <input type="number" name="sort_order" min="0" x-model="editCat.sort_order">
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-color)">
                    <button type="button" @click="editing = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
