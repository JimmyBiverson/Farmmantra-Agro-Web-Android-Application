@extends('layouts.app')
@section('title', 'News & Events')
@section('page-title', 'News & Events')

@section('content')
<div x-data="{ open: false, editing: false, editArticle: {} }">
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">News & Events ({{ $news->total() }})</h3>
            <button @click="open = true" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">
                <i class="fas fa-plus mr-1"></i> Add Article
            </button>
        </div>
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="w-full table-dark">
                    <thead>
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <th class="px-4 py-3 text-left">Title</th>
                            <th class="px-4 py-3 text-left">Excerpt</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-left">Published</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($news as $article)
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $article->title }}</td>
                            <td class="px-4 py-3 text-sm max-w-xs truncate" style="color:var(--text-secondary)">{{ $article->excerpt }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge {{ $article->is_published ? 'badge-success' : 'badge-warning' }}">{{ $article->is_published ? 'Published' : 'Draft' }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">{{ $article->published_at?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" class="btn-action" title="Edit" style="color:var(--accent); background:rgba(99,102,241,0.12)"
                                        @click="editArticle = {{ \Illuminate\Support\Js::from(['id' => $article->id, 'title' => $article->title, 'excerpt' => (string) $article->excerpt, 'body' => (string) $article->body, 'is_published' => (bool) $article->is_published]) }}; editing = true">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form action="{{ route('web.admin.news.delete') }}" method="POST" class="inline" onsubmit="return confirm('Delete article {{ addslashes($article->title) }}?')">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $article->id }}">
                                        <button type="submit" class="btn-delete"><i class="fas fa-trash-can text-xs"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No articles yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $news->links() }}</div>
    </div>

    <!-- Add Article Modal -->
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="open = false">
        <div class="modal-backdrop" @click="open = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Add Article</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Publish a news article or event announcement.</p>
                </div>
                <button @click="open = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.news.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Title *</label>
                    <input type="text" name="title" required placeholder="Article headline">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Excerpt</label>
                    <input type="text" name="excerpt" placeholder="Brief summary...">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Body</label>
                    <textarea name="body" rows="6" placeholder="Full article content..."></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-color)">
                    <button type="button" @click="open = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Publish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Article Modal -->
    <div x-show="editing" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="editing = false">
        <div class="modal-backdrop" @click="editing = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Edit Article</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Update the article content.</p>
                </div>
                <button @click="editing = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.news.update') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="id" :value="editArticle.id">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Title *</label>
                    <input type="text" name="title" required x-model="editArticle.title">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Excerpt</label>
                    <input type="text" name="excerpt" x-model="editArticle.excerpt">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Body</label>
                    <textarea name="body" rows="6" x-model="editArticle.body"></textarea>
                </div>
                <div class="flex items-center justify-between pt-2 border-t" style="border-color:var(--border-color)">
                    <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-secondary)">
                        <input type="hidden" name="is_published" value="0">
                        <input type="checkbox" name="is_published" value="1" :checked="!!editArticle.is_published" @change="editArticle.is_published = $event.target.checked">
                        Published
                    </label>
                    <div class="flex gap-3">
                        <button type="button" @click="editing = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Update Article</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
