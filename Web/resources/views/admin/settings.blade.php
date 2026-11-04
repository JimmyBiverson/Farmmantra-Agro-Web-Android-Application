@extends('layouts.app')
@section('title', 'System Settings')
@section('page-title', 'System Settings')

@php
    $settingsNav = [
        ['route' => 'web.admin.settings.site', 'label' => 'Site Identity', 'icon' => 'fa-palette'],
        ['route' => 'web.admin.settings.general', 'label' => 'General', 'icon' => 'fa-cog'],
        ['route' => 'web.admin.settings.users', 'label' => 'User Management', 'icon' => 'fa-user-gear'],
        ['route' => 'web.admin.settings.roles', 'label' => 'Roles & Permissions', 'icon' => 'fa-shield-halved'],
        ['route' => 'web.admin.settings.notifications', 'label' => 'Notifications', 'icon' => 'fa-bell'],
        ['route' => 'web.admin.settings.system', 'label' => 'System Info', 'icon' => 'fa-server'],
    ];
@endphp

@section('content')
{{-- Settings Tab Bar --}}
<div class="card-full mb-6">
    <div class="card-body py-4 px-5">
        <div class="flex gap-2 overflow-x-auto" style="scrollbar-width:none">
            @foreach($settingsNav as $nav)
            <a href="{{ route($nav['route']) }}"
               class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold whitespace-nowrap transition-all {{ request()->routeIs($nav['route']) ? 'text-white shadow-lg' : '' }}"
               style="{{ request()->routeIs($nav['route'])
                   ? 'background:linear-gradient(135deg,#6366f1,#8b5cf6); box-shadow:0 4px 15px rgba(99,102,241,0.4)'
                   : 'color:var(--text-primary); background:var(--bg-card); border:1px solid var(--border-color)' }}">
                <i class="fas {{ $nav['icon'] }} {{ request()->routeIs($nav['route']) ? '' : 'opacity-60' }}"></i>
                {{ $nav['label'] }}
            </a>
            @endforeach
        </div>
    </div>
</div>

{{-- Settings Content --}}
<div class="card-full">
    <div class="card-header">
        <div>
            <p class="text-xs" style="color:var(--text-muted)">Farmmantra Agro Chemicals Limited</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge badge-success">v1.0</span>
        </div>
    </div>
    <div class="card-body">
        <div class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Company Name</label>
                    <input type="text" value="{{ $site['site_name'] ?? 'Farmmantra Agro Chemicals Limited' }}" disabled class="w-full rounded-lg border px-3 py-2.5 text-sm opacity-60" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">System Email</label>
                    <input type="email" value="{{ $site['site_email'] ?? 'info@farmmantra.co.ug' }}" disabled class="w-full rounded-lg border px-3 py-2.5 text-sm opacity-60" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Phone</label>
                    <input type="text" value="{{ $site['site_phone'] ?? '+256 700 000000' }}" disabled class="w-full rounded-lg border px-3 py-2.5 text-sm opacity-60" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Currency</label>
                    <input type="text" value="UGX (Ugandan Shilling)" disabled class="w-full rounded-lg border px-3 py-2.5 text-sm opacity-60" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Timezone</label>
                    <input type="text" value="Africa/Kampala (EAT)" disabled class="w-full rounded-lg border px-3 py-2.5 text-sm opacity-60" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Active Franchises</label>
                    <input type="text" value="{{ \App\Models\Franchise::where('is_active', true)->count() }}" disabled class="w-full rounded-lg border px-3 py-2.5 text-sm opacity-60" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Company Address</label>
                <textarea rows="2" disabled class="w-full rounded-lg border px-3 py-2.5 text-sm opacity-60" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">{{ $site['site_address'] ?? 'Kampala, Uganda' }}</textarea>
            </div>
            <div class="p-4 rounded-xl border" style="border-color:rgba(99,102,241,0.2); background:rgba(99,102,241,0.05)">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle mt-0.5" style="color:var(--accent)"></i>
                    <div>
                        <p class="text-sm font-semibold" style="color:var(--text-primary)">Read-Only Settings</p>
                        <p class="text-xs mt-1" style="color:var(--text-secondary)">Company name, contact info, and other core settings are managed in the <a href="{{ route('web.admin.settings.site') }}" class="underline font-semibold" style="color:var(--accent)">Site Identity</a> tab.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
