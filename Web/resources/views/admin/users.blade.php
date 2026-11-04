@extends('layouts.app')
@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div x-data="{ open: false, editing: false, editUser: {} }">
    <div class="card-full">
        <div class="card-header">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Users ({{ $users->total() }})</h3>
            <button @click="open = true" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">
                <i class="fas fa-plus mr-1"></i> Add User
            </button>
        </div>
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="w-full table-dark">
                    <thead>
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Role</th>
                            <th class="px-4 py-3 text-left">Franchise</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $u)
                        <tr class="border-b" style="border-color:var(--border-color)">
                            <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $u->name }}</td>
                            <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $u->email }}</td>
                            <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $u->role?->name }}</td>
                            <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $u->franchise?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge {{ $u->is_active ? 'badge-success' : 'badge-danger' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if(auth()->id() !== $u->id)
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" class="btn-action" title="Edit" style="color:var(--accent); background:rgba(99,102,241,0.12)"
                                        @click="editUser = {{ \Illuminate\Support\Js::from(['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role_id' => (string) $u->role_id, 'franchise_id' => $u->franchise_id ? (string) $u->franchise_id : '', 'phone' => (string) $u->phone]) }}; editing = true">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form action="{{ route('web.admin.users.toggle') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $u->id }}">
                                        <button type="submit" class="btn-action {{ $u->is_active ? 'btn-decline' : 'btn-approve' }}" title="{{ $u->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="fas {{ $u->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                        </button>
                                    </form>
                                </div>
                                @else
                                <span class="text-xs" style="color:var(--text-muted)">You</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No users found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $users->links() }}</div>
    </div>

    <!-- Add User Modal -->
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="open = false">
        <div class="modal-backdrop" @click="open = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Add New User</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Create a new user account with role assignment.</p>
                </div>
                <button @click="open = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.users.store') }}" method="POST" class="space-y-4" onsubmit="return validatePasswordMatch(this)">
                @csrf
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Full Name *</label>
                    <input type="text" name="name" required placeholder="e.g. John Okello">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Email *</label>
                    <input type="email" name="email" required placeholder="user@example.com">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Password *</label>
                        <div style="position:relative; width:100%;">
                            <input type="password" id="admin-create-password" name="password" required minlength="6" placeholder="Min 6 characters" style="width:100%; padding-right:2.75rem;">
                            <button type="button" data-toggle-password="admin-create-password" aria-label="Show or hide password" style="position:absolute; top:50%; right:0.75rem; transform:translateY(-50%); z-index:2; color:var(--text-muted); background:transparent; border:0; padding:0.25rem; cursor:pointer;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Confirm Password *</label>
                        <div style="position:relative; width:100%;">
                            <input type="password" id="admin-create-password-confirmation" name="password_confirmation" required placeholder="Re-enter password" style="width:100%; padding-right:2.75rem;">
                            <button type="button" data-toggle-password="admin-create-password-confirmation" aria-label="Show or hide password confirmation" style="position:absolute; top:50%; right:0.75rem; transform:translateY(-50%); z-index:2; color:var(--text-muted); background:transparent; border:0; padding:0.25rem; cursor:pointer;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="password-match-error hidden text-xs mt-1 text-red-500">Passwords do not match. Please re-enter to confirm.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Role *</label>
                        <select name="role_id" required>
                            <option value="">Select role</option>
                            @foreach(\App\Models\Role::orderBy('name')->get() as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Franchise</label>
                        <select name="franchise_id">
                            <option value="">None</option>
                            @foreach(\App\Models\Franchise::orderBy('name')->get() as $f)
                            <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Phone</label>
                    <input type="text" name="phone" placeholder="+256...">
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-color)">
                    <button type="button" @click="open = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Save User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div x-show="editing" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="modal-overlay" style="display:none" @keydown.escape.window="editing = false">
        <div class="modal-backdrop" @click="editing = false"></div>
        <div class="modal-panel" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-lg font-bold" style="color:var(--text-primary)">Edit User</h3>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted)">Update user details. Leave password blank to keep it unchanged.</p>
                </div>
                <button @click="editing = false" class="btn-delete" style="color:var(--text-muted);width:2rem;height:2rem"><i class="fas fa-times"></i></button>
            </div>
            <form action="{{ route('web.admin.users.update') }}" method="POST" class="space-y-4" onsubmit="return validatePasswordMatch(this)">
                @csrf
                <input type="hidden" name="id" :value="editUser.id">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Full Name *</label>
                    <input type="text" name="name" required x-model="editUser.name">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Email *</label>
                    <input type="email" name="email" required x-model="editUser.email">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">New Password</label>
                        <div style="position:relative; width:100%;">
                            <input type="password" id="admin-edit-password" name="password" minlength="6" placeholder="Leave blank to keep" style="width:100%; padding-right:2.75rem;">
                            <button type="button" data-toggle-password="admin-edit-password" aria-label="Show or hide password" style="position:absolute; top:50%; right:0.75rem; transform:translateY(-50%); z-index:2; color:var(--text-muted); background:transparent; border:0; padding:0.25rem; cursor:pointer;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Confirm Password</label>
                        <div style="position:relative; width:100%;">
                            <input type="password" id="admin-edit-password-confirmation" name="password_confirmation" placeholder="Re-enter password" style="width:100%; padding-right:2.75rem;">
                            <button type="button" data-toggle-password="admin-edit-password-confirmation" aria-label="Show or hide password confirmation" style="position:absolute; top:50%; right:0.75rem; transform:translateY(-50%); z-index:2; color:var(--text-muted); background:transparent; border:0; padding:0.25rem; cursor:pointer;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="password-match-error hidden text-xs mt-1 text-red-500">Passwords do not match. Please re-enter to confirm.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Role *</label>
                        <select name="role_id" required x-model="editUser.role_id">
                            <option value="">Select role</option>
                            @foreach(\App\Models\Role::orderBy('name')->get() as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Franchise</label>
                        <select name="franchise_id" x-model="editUser.franchise_id">
                            <option value="">None</option>
                            @foreach(\App\Models\Franchise::orderBy('name')->get() as $f)
                            <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Phone</label>
                    <input type="text" name="phone" x-model="editUser.phone" placeholder="+256...">
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-color)">
                    <button type="button" @click="editing = false" class="px-5 py-2.5 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25"><i class="fas fa-save mr-1.5"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function togglePasswordVisibility(inputId) {
        const button = document.querySelector(`[data-toggle-password="${inputId}"]`);
        const input = button?.parentElement?.querySelector(`#${inputId}`) || document.getElementById(inputId);
        if (!input) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        if (button) {
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        }
    }

    function validatePasswordMatch(form) {
        const passwordFields = [
            { field: form.querySelector('[name="password"]'), confirmField: form.querySelector('[name="password_confirmation"]') },
        ].filter(item => item.field && item.confirmField);

        let isValid = true;

        passwordFields.forEach(({ field, confirmField }) => {
            const hasPassword = field.value.trim() !== '';
            const confirmValue = confirmField.value;
            const errorNode = confirmField.closest('div')?.querySelector('.password-match-error');

            if (hasPassword && confirmValue !== field.value) {
                isValid = false;
                confirmField.setCustomValidity('Passwords do not match.');
                if (errorNode) errorNode.classList.remove('hidden');
            } else {
                confirmField.setCustomValidity('');
                if (errorNode) errorNode.classList.add('hidden');
            }
        });

        if (!isValid) {
            form.reportValidity();
            return false;
        }

        return true;
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-toggle-password]');
        if (!button) return;
        togglePasswordVisibility(button.dataset.togglePassword);
    });
</script>
@endpush
