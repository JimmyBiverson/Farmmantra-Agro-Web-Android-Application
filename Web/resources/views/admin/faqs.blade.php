@extends('layouts.app')
@section('title', 'FAQs')
@section('page-title', 'Frequently Asked Questions')

@section('content')
<div x-data="{ editing: false, editFaq: {} }">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1">
        <div class="card-full">
            <div class="card-header">
                <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Add FAQ</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('web.admin.faqs.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Question *</label>
                        <input type="text" name="question" required placeholder="e.g. How do I place an order?" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Answer *</label>
                        <textarea name="answer" rows="5" required placeholder="Detailed answer..." class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                        <i class="fas fa-save mr-1"></i> Save FAQ
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="card-full">
            <div class="card-header">
                <h3 class="text-sm font-semibold" style="color:var(--text-primary)">All FAQs ({{ $faqs->count() }})</h3>
            </div>
            <div class="card-body space-y-3">
                @forelse($faqs as $faq)
                <div class="rounded-xl p-4 border" style="border-color:var(--border-color); background:var(--bg-card)">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-mono" style="color:var(--text-muted)">#{{ $faq->sort_order }}</span>
                                <span class="badge {{ $faq->is_active ? 'badge-success' : 'badge-danger' }}">{{ $faq->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <p class="text-sm font-semibold mb-1.5" style="color:var(--text-primary)">{{ $faq->question }}</p>
                            <p class="text-xs leading-relaxed" style="color:var(--text-secondary)">{{ Str::limit($faq->answer, 150) }}</p>
                        </div>
                        <div class="flex gap-1 flex-shrink-0">
                            <button type="button" class="btn-action" title="Edit" style="color:var(--accent); background:rgba(99,102,241,0.12)"
                                @click="editFaq = {{ \Illuminate\Support\Js::from(['id' => $faq->id, 'question' => $faq->question, 'answer' => $faq->answer, 'sort_order' => (string) $faq->sort_order, 'is_active' => (bool) $faq->is_active]) }}; editing = true">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form action="{{ route('web.admin.faqs.delete') }}" method="POST" onsubmit="return confirm('Delete this FAQ?')">
                                @csrf
                                <input type="hidden" name="id" value="{{ $faq->id }}">
                                <button type="submit" class="btn-delete"><i class="fas fa-trash-can text-xs"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8">
                    <i class="fas fa-circle-question text-3xl mb-3" style="color:var(--text-muted)"></i>
                    <p class="text-sm" style="color:var(--text-muted)">No FAQs yet</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Edit FAQ Modal -->
    <div x-show="editing" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="editing = false">
        <div class="modal-backdrop" @click="editing = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold" style="color:var(--text-primary)">Edit FAQ</h3>
                <button @click="editing = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.faqs.update') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="id" :value="editFaq.id">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Question *</label>
                    <input type="text" name="question" required x-model="editFaq.question">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Answer *</label>
                    <textarea name="answer" rows="5" required x-model="editFaq.answer"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Sort Order</label>
                    <input type="number" name="sort_order" min="0" x-model="editFaq.sort_order">
                </div>
                <div class="flex items-center justify-between pt-2 border-t" style="border-color:var(--border-color)">
                    <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-secondary)">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" :checked="!!editFaq.is_active" @change="editFaq.is_active = $event.target.checked">
                        Active
                    </label>
                    <div class="flex gap-3">
                        <button type="button" @click="editing = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Update FAQ</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
