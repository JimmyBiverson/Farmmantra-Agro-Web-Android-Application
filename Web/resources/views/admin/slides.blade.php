@extends('layouts.app')
@section('title', 'Slides / Banners')
@section('page-title', 'Slides & Banners')

@section('content')
<div x-data="{ editing: false, editSlide: {} }">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1">
        <div class="card-full">
            <div class="card-header">
                <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Add Slide</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('web.admin.slides.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Title *</label>
                        <input type="text" name="title" required placeholder="Banner headline" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Subtitle</label>
                        <input type="text" name="subtitle" placeholder="Supporting text" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Image</label>
                        <input type="file" name="image" accept="image/*" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Button Text</label>
                        <input type="text" name="button_text" placeholder="e.g. Shop Now" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Button URL</label>
                        <input type="url" name="button_url" placeholder="https://..." class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                        <i class="fas fa-save mr-1"></i> Save Slide
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="card-full">
            <div class="card-header">
                <h3 class="text-sm font-semibold" style="color:var(--text-primary)">All Slides ({{ $slides->count() }})</h3>
            </div>
            <div class="card-body p-0">
                <div class="overflow-x-auto">
                    <table class="w-full table-dark">
                        <thead>
                            <tr class="border-b" style="border-color:var(--border-color)">
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Title</th>
                                <th class="px-4 py-3 text-left">Button</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($slides as $slide)
                            <tr class="border-b" style="border-color:var(--border-color)">
                                <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $slide->sort_order }}</td>
                                <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $slide->title }}</td>
                                <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $slide->button_text ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge {{ $slide->is_active ? 'badge-success' : 'badge-danger' }}">{{ $slide->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" class="btn-action" title="Edit" style="color:var(--accent); background:rgba(99,102,241,0.12)"
                                            @click="editSlide = {{ \Illuminate\Support\Js::from(['id' => $slide->id, 'title' => $slide->title, 'subtitle' => (string) $slide->subtitle, 'button_text' => (string) $slide->button_text, 'button_url' => (string) $slide->button_url, 'sort_order' => (string) $slide->sort_order, 'image_url' => $slide->image_url, 'is_active' => (bool) $slide->is_active]) }}; editing = true">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form action="{{ route('web.admin.slides.delete') }}" method="POST" class="inline" onsubmit="return confirm('Delete slide {{ addslashes($slide->title) }}?')">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $slide->id }}">
                                            <button type="submit" class="btn-delete"><i class="fas fa-trash-can text-xs"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No slides yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Slide Modal -->
    <div x-show="editing" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="editing = false">
        <div class="modal-backdrop" @click="editing = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold" style="color:var(--text-primary)">Edit Slide</h3>
                <button @click="editing = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.slides.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="id" :value="editSlide.id">
                <div class="flex items-center gap-4 mb-1">
                    <template x-if="editSlide.image_url">
                        <img :src="editSlide.image_url" alt="Current image" style="width:5rem;height:3rem;border-radius:0.5rem;object-fit:cover;border:1px solid var(--border-color)">
                    </template>
                    <div class="flex-1">
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Replace Image</label>
                        <input type="file" name="image" accept="image/*" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Title *</label>
                    <input type="text" name="title" required x-model="editSlide.title">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Subtitle</label>
                    <input type="text" name="subtitle" x-model="editSlide.subtitle">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Button Text</label>
                        <input type="text" name="button_text" x-model="editSlide.button_text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Button URL</label>
                        <input type="url" name="button_url" x-model="editSlide.button_url">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Sort Order</label>
                    <input type="number" name="sort_order" min="0" x-model="editSlide.sort_order">
                </div>
                <div class="flex items-center justify-between pt-2 border-t" style="border-color:var(--border-color)">
                    <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-secondary)">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" :checked="!!editSlide.is_active" @change="editSlide.is_active = $event.target.checked">
                        Active
                    </label>
                    <div class="flex gap-3">
                        <button type="button" @click="editing = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Update Slide</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
