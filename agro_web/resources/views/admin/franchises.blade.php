@extends('layouts.app')
@section('title', 'Franchises')
@section('page-title', 'Franchise Management')

@section('content')
<div x-data="{ open: false, editing: false, editFranchise: {} }">
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Franchises ({{ $franchises->total() }})</h3>
            <button @click="open = true" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">
                <i class="fas fa-plus mr-1"></i> Create Franchise
            </button>
        </div>
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="w-full table-dark">
                    <thead>
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Region</th>
                            <th class="px-4 py-3 text-left">Contact</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($franchises as $f)
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <td class="px-4 py-3 text-sm font-medium" style="color:var(--accent)">{{ $f->code }}</td>
                            <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $f->name }}</td>
                            <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $f->region }}</td>
                            <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $f->contact_person }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold" style="color:var(--text-primary)">UGX {{ number_format($f->account_balance) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge {{ $f->is_active ? 'badge-success' : 'badge-danger' }}">{{ $f->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button" class="btn-action" title="Edit" style="color:var(--accent); background:rgba(99,102,241,0.12)"
                                        @click="editFranchise = {{ \Illuminate\Support\Js::from(['id' => $f->id, 'name' => $f->name, 'code' => $f->code, 'contact_person' => (string) $f->contact_person, 'phone' => (string) $f->phone, 'email' => (string) $f->email, 'region' => (string) $f->region, 'region_id' => $f->region_id ? (string) $f->region_id : '', 'address' => (string) $f->address, 'credit_limit' => (string) $f->credit_limit]) }}; editing = true">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form action="{{ route('web.admin.franchises.toggle') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $f->id }}">
                                        <button type="submit" class="btn-delete" style="width:2rem;height:2rem;display:inline-flex;align-items:center;justify-content:center" title="{{ $f->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="fas fa-{{ $f->is_active ? 'ban text-xs' : 'check text-xs' }}"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('web.admin.franchises.delete') }}" method="POST" class="inline" onsubmit="return confirm('Delete franchise {{ addslashes($f->name) }}? This cannot be undone.')">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $f->id }}">
                                        <button type="submit" class="btn-delete"><i class="fas fa-trash-can text-xs"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No franchises found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $franchises->links() }}</div>
    </div>

    <!-- Create Franchise Modal -->
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="open = false">
        <div class="modal-backdrop" @click="open = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Create New Franchise</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Fill in the details below to register a new franchise.</p>
                </div>
                <button @click="open = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.franchises.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Franchise Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Agro Mart Kampala" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Code *</label>
                        <input type="text" name="code" required placeholder="e.g. FRN-001" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Contact Person</label>
                        <input type="text" name="contact_person" placeholder="e.g. John Doe" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Phone</label>
                        <input type="text" name="phone" placeholder="e.g. 0700 123456" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Email</label>
                        <input type="email" name="email" placeholder="e.g. franchise@example.com" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Region (customers visible to this franchise)</label>
                        <select name="region_id" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                            <option value="">— none —</option>
                            @foreach(\App\Models\Region::orderBy('name')->get() as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Address</label>
                    <input type="text" name="address" placeholder="e.g. Plot 12, Kampala Road" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Credit Limit (UGX)</label>
                    <input type="number" name="credit_limit" min="0" placeholder="0" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-color)">
                    <button type="button" @click="open = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Save Franchise</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Franchise Modal -->
    <div x-show="editing" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="editing = false">
        <div class="modal-backdrop" @click="editing = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Edit Franchise</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Update franchise details.</p>
                </div>
                <button @click="editing = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.franchises.update') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="id" :value="editFranchise.id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Franchise Name *</label>
                        <input type="text" name="name" required x-model="editFranchise.name">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Code *</label>
                        <input type="text" name="code" required x-model="editFranchise.code">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Contact Person</label>
                        <input type="text" name="contact_person" x-model="editFranchise.contact_person">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Phone</label>
                        <input type="text" name="phone" x-model="editFranchise.phone">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Email</label>
                        <input type="email" name="email" x-model="editFranchise.email">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Region (customers visible to this franchise)</label>
                        <select name="region_id" x-model="editFranchise.region_id">
                            <option value="">— none —</option>
                            @foreach(\App\Models\Region::orderBy('name')->get() as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Address</label>
                    <input type="text" name="address" x-model="editFranchise.address">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Credit Limit (UGX)</label>
                    <input type="number" name="credit_limit" min="0" x-model="editFranchise.credit_limit">
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-color)">
                    <button type="button" @click="editing = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Update Franchise</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
