@extends('layouts.app')
@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('content')
<div class="space-y-6 page-enter" x-data="{ activeTab: 'personal' }">

    {{-- Profile Header --}}
    <div class="card-full relative overflow-hidden">
        <div class="absolute inset-0 opacity-[0.03]" style="background:linear-gradient(135deg, var(--accent) 0%, transparent 50%)"></div>
        <div class="card-body p-6 sm:p-8 relative">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                {{-- Avatar --}}
                <div class="relative group" x-data="{ uploading: false }">
                    <form action="{{ route('web.profile.avatar') }}" method="POST" enctype="multipart/form-data" id="avatarForm" @submit="uploading = true">
                        @csrf
                        <label class="block cursor-pointer">
                            <div class="h-24 w-24 sm:h-28 sm:w-28 rounded-2xl overflow-hidden border-4 transition-all group-hover:shadow-lg" style="border-color:var(--border-color); box-shadow:var(--shadow)">
                                @if($user->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar))
                                <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                                @else
                                <div class="h-full w-full gradient-indigo flex items-center justify-center">
                                    <span class="text-white font-bold text-3xl">{{ substr($user->name, 0, 1) }}</span>
                                </div>
                                @endif
                            </div>
                            <div class="absolute inset-0 rounded-2xl flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity">
                                <div class="text-center">
                                    <i x-show="!uploading" class="fas fa-camera text-white text-lg"></i>
                                    <i x-show="uploading" class="fas fa-spinner fa-spin text-white text-lg"></i>
                                    <p class="text-white text-[10px] font-medium mt-1" x-text="uploading ? 'Uploading...' : 'Change Photo'"></p>
                                </div>
                            </div>
                            <input type="file" name="avatar" accept="image/*" class="hidden" onchange="if(this.files[0].size > 2097152){alert('Image must be under 2MB'); this.value=''; return;} document.getElementById('avatarForm').submit()">
                        </label>
                    </form>
                    <p class="text-[10px] text-center mt-2" style="color:var(--text-muted)">Click to upload · JPEG/PNG/WebP · Max 2MB</p>
                </div>

                {{-- User Info --}}
                <div class="flex-1 text-center sm:text-left">
                    <h2 class="text-xl font-bold" style="color:var(--text-primary)">{{ $user->name }}</h2>
                    <p class="text-sm mt-0.5" style="color:var(--text-muted)">{{ $user->email }}</p>
                    <div class="flex flex-wrap justify-center sm:justify-start gap-2 mt-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold" style="background:rgba(99,102,241,0.1); color:var(--accent)">
                            <i class="fas fa-shield-halved"></i> {{ $user->role?->name ?? 'N/A' }}
                        </span>
                        @if($user->franchise)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold" style="background:rgba(16,185,129,0.1); color:var(--success)">
                            <i class="fas fa-store"></i> {{ $user->franchise->name }}
                        </span>
                        @endif
                        @if($user->employee_id)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold" style="background:rgba(139,92,246,0.1); color:#8b5cf6">
                            <i class="fas fa-id-badge"></i> #{{ $user->employee_id }}
                        </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold {{ $user->is_active ? '' : 'bg-red-500/10 text-red-500' }}" style="{{ $user->is_active ? 'background:rgba(16,185,129,0.1); color:var(--success)' : '' }}">
                            <i class="fas {{ $user->is_active ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i> {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="text-xs mt-3" style="color:var(--text-muted)">
                        <i class="fas fa-clock mr-1"></i>
                        Last login: {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                        <span class="mx-2">·</span>
                        Member since {{ $user->created_at->format('M Y') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Bar --}}
    <div class="card-full">
        <div class="card-body py-3 px-4 sm:px-5">
            <div class="flex gap-2 overflow-x-auto" style="scrollbar-width:none">
                <button @click="activeTab = 'personal'" class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold whitespace-nowrap transition-all"
                    :style="activeTab === 'personal' ? 'background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; box-shadow:0 4px 15px rgba(99,102,241,0.4)' : 'color:var(--text-primary); background:var(--bg-card); border:1px solid var(--border-color)'">
                    <i class="fas fa-user-pen"></i> Personal Details
                </button>
                <button @click="activeTab = 'password'" class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold whitespace-nowrap transition-all"
                    :style="activeTab === 'password' ? 'background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; box-shadow:0 4px 15px rgba(99,102,241,0.4)' : 'color:var(--text-primary); background:var(--bg-card); border:1px solid var(--border-color)'">
                    <i class="fas fa-lock"></i> Change Password
                </button>
                <button @click="activeTab = 'account'" class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold whitespace-nowrap transition-all"
                    :style="activeTab === 'account' ? 'background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; box-shadow:0 4px 15px rgba(99,102,241,0.4)' : 'color:var(--text-primary); background:var(--bg-card); border:1px solid var(--border-color)'">
                    <i class="fas fa-circle-info"></i> Account Info
                </button>
            </div>
        </div>
    </div>

    {{-- Personal Details Tab --}}
    <div x-show="activeTab === 'personal'" x-transition.opacity>
        <div class="card-full">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Personal Information</h3>
                    <p class="text-xs" style="color:var(--text-muted)">Update your personal details</p>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('web.profile.update') }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Full Name</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                                   class="w-full rounded-lg border px-3 py-2.5 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                   onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                            @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Email Address</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                                   class="w-full rounded-lg border px-3 py-2.5 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                   onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                            @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Phone Number</label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+256 7XX XXX XXX"
                                   class="w-full rounded-lg border px-3 py-2.5 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                   onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                            @error('phone')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Gender</label>
                            <select name="gender"
                                    class="w-full rounded-lg border px-3 py-2.5 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                    onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                                <option value="">Select gender</option>
                                <option value="male" {{ old('gender', $user->gender) === 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender', $user->gender) === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ old('gender', $user->gender) === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}"
                                   class="w-full rounded-lg border px-3 py-2.5 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                   onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                            @error('date_of_birth')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Address</label>
                            <input type="text" name="address" value="{{ old('address', $user->address) }}" placeholder="Street, City, District"
                                   class="w-full rounded-lg border px-3 py-2.5 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                   onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                            @error('address')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex justify-end mt-6">
                        <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all" style="background:linear-gradient(135deg,#6366f1,#8b5cf6); box-shadow:0 4px 15px rgba(99,102,241,0.4)" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 20px rgba(99,102,241,0.5)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 15px rgba(99,102,241,0.4)'">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Password Tab --}}
    <div x-show="activeTab === 'password'" x-transition.opacity>
        <div class="card-full">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Change Password</h3>
                    <p class="text-xs" style="color:var(--text-muted)">Ensure your account stays secure with a strong password</p>
                </div>
            </div>
            <div class="card-body" x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
                <form action="{{ route('web.profile.password') }}" method="POST">
                    @csrf
                    <div class="max-w-lg space-y-5">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Current Password</label>
                            <div class="relative">
                                <input :type="showCurrent ? 'text' : 'password'" name="current_password" required
                                       class="w-full rounded-lg border px-3 py-2.5 pr-10 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                       onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                                <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 transition" style="color:var(--text-muted)">
                                    <i :class="showCurrent ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-sm"></i>
                                </button>
                            </div>
                            @error('current_password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">New Password</label>
                            <div class="relative">
                                <input :type="showNew ? 'text' : 'password'" name="password" required minlength="8"
                                       class="w-full rounded-lg border px-3 py-2.5 pr-10 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                       onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                                <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 transition" style="color:var(--text-muted)">
                                    <i :class="showNew ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-sm"></i>
                                </button>
                            </div>
                            <p class="text-[10px] mt-1" style="color:var(--text-muted)">Minimum 8 characters</p>
                            @error('password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Confirm New Password</label>
                            <div class="relative">
                                <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required
                                       class="w-full rounded-lg border px-3 py-2.5 pr-10 text-sm transition" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"
                                       onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-color)'">
                                <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 transition" style="color:var(--text-muted)">
                                    <i :class="showConfirm ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6 p-4 rounded-xl" style="background:rgba(139,92,246,0.05); border:1px solid rgba(139,92,246,0.15)">
                        <i class="fas fa-shield-halved text-lg" style="color:var(--accent)"></i>
                        <div>
                            <p class="text-xs font-semibold" style="color:var(--text-primary)">Security Tip</p>
                            <p class="text-[10px]" style="color:var(--text-muted)">Use a mix of letters, numbers, and symbols for a strong password. Never share your password with anyone.</p>
                        </div>
                    </div>
                    <div class="flex justify-end mt-6">
                        <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all" style="background:linear-gradient(135deg,#6366f1,#8b5cf6); box-shadow:0 4px 15px rgba(99,102,241,0.4)" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                            <i class="fas fa-key mr-2"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Account Info Tab --}}
    <div x-show="activeTab === 'account'" x-transition.opacity>
        <div class="card-full">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Account Information</h3>
                    <p class="text-xs" style="color:var(--text-muted)">View your account details and role information</p>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Account Details --}}
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted)">Account Details</h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">User ID</span>
                                <span class="text-sm font-semibold" style="color:var(--text-primary)">#{{ $user->id }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Email</span>
                                <span class="text-sm" style="color:var(--text-primary)">{{ $user->email }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Employee ID</span>
                                <span class="text-sm" style="color:var(--text-primary)">{{ $user->employee_id ?: 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Status</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-semibold {{ $user->is_active ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' }}">
                                    <i class="fas {{ $user->is_active ? 'fa-circle-check' : 'fa-circle-xmark' }} text-[8px]"></i>
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Role</span>
                                <span class="text-sm font-medium" style="color:var(--accent)">{{ $user->role?->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Organization Details --}}
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted)">Organization</h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Branch</span>
                                <span class="text-sm" style="color:var(--text-primary)">{{ $user->branch?->name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Franchise</span>
                                <span class="text-sm" style="color:var(--text-primary)">{{ $user->franchise?->name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Franchise Code</span>
                                <span class="text-sm font-mono" style="color:var(--text-primary)">{{ $user->franchise?->code ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Created</span>
                                <span class="text-sm" style="color:var(--text-primary)">{{ $user->created_at->format('M d, Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 border-b" style="border-color:var(--border-color)">
                                <span class="text-xs font-medium" style="color:var(--text-muted)">Last Updated</span>
                                <span class="text-sm" style="color:var(--text-primary)">{{ $user->updated_at->format('M d, Y H:i') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
