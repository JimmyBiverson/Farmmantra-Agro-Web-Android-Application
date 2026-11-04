@extends('layouts.app')
@section('title', 'Pages')
@section('page-title', 'CMS Pages')

@section('content')
<div x-data="{ open: false, editing: false, editPage: {} }">
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Pages ({{ $pages->total() }})</h3>
            <button @click="open = true" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">
                <i class="fas fa-plus mr-1"></i> Add Page
            </button>
        </div>
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="w-full table-dark">
                    <thead>
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <th class="px-4 py-3 text-left">Title</th>
                            <th class="px-4 py-3 text-left">Slug</th>
                            <th class="px-4 py-3 text-left">Meta Description</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pages as $page)
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $page->title }}</td>
                            <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">/{{ $page->slug }}</td>
                            <td class="px-4 py-3 text-sm max-w-xs truncate" style="color:var(--text-muted)">{{ $page->meta_description ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge {{ $page->is_published ? 'badge-success' : 'badge-warning' }}">{{ $page->is_published ? 'Published' : 'Draft' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" class="btn-action" title="Edit" style="color:var(--accent); background:rgba(99,102,241,0.12)"
                                        @click="editPage = {{ \Illuminate\Support\Js::from(['id' => $page->id, 'title' => $page->title, 'body' => (string) $page->body, 'meta_description' => (string) $page->meta_description, 'is_published' => (bool) $page->is_published]) }}; editing = true">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form action="{{ route('web.admin.pages.delete') }}" method="POST" class="inline" onsubmit="return confirm('Delete page {{ addslashes($page->title) }}?')">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $page->id }}">
                                        <button type="submit" class="btn-delete"><i class="fas fa-trash-can text-xs"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No pages yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $pages->links() }}</div>
    </div>

    <!-- Add Page Modal -->
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="open = false">
        <div class="modal-backdrop" @click="open = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Add Page</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Create a new CMS page for the site.</p>
                </div>
                <button @click="open = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.pages.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Title *</label>
                    <input type="text" name="title" required placeholder="Page title">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Body</label>
                    <textarea name="body" rows="8" placeholder="Page content..."></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Meta Description</label>
                    <input type="text" name="meta_description" placeholder="SEO description...">
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-color)">
                    <button type="button" @click="open = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Publish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Page Modal -->
    <div x-show="editing" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="editing = false">
        <div class="modal-backdrop" @click="editing = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Edit Page</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Update the CMS page content.</p>
                </div>
                <button @click="editing = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.pages.update') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="id" :value="editPage.id">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Title *</label>
                    <input type="text" name="title" required x-model="editPage.title">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Body</label>
                    <textarea name="body" rows="8" x-model="editPage.body"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Meta Description</label>
                    <input type="text" name="meta_description" x-model="editPage.meta_description">
                </div>
                <div class="flex items-center justify-between pt-2 border-t" style="border-color:var(--border-color)">
                    <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-secondary)">
                        <input type="hidden" name="is_published" value="0">
                        <input type="checkbox" name="is_published" value="1" :checked="!!editPage.is_published" @change="editPage.is_published = $event.target.checked">
                        Published
                    </label>
                    <div class="flex gap-3">
                        <button type="button" @click="editing = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Update Page</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
