@extends('layouts.app')
@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')

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

<div class="card-full">
    <div class="card-header"><h3 class="text-sm font-semibold" style="color:var(--text-primary)">System Roles</h3></div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
        <table class="w-full table-dark">
            <thead><tr class="border-b" style="border-color:var(--border-color)">
                <th class="px-4 py-3 text-left">Role</th>
                <th class="px-4 py-3 text-center">Users</th>
                <th class="px-4 py-3 text-left">Description</th>
            </tr></thead>
            <tbody>
                @foreach($roles as $role)
                <tr class="border-b" style="border-color:var(--border-color)">
                    <td class="px-4 py-3"><span class="badge badge-primary">{{ $role->name }}</span></td>
                    <td class="px-4 py-3 text-sm text-center" style="color:var(--text-secondary)">{{ $role->users_count ?? $role->users()->count() }}</td>
                    <td class="px-4 py-3 text-sm" style="color:var(--text-muted)">
                        @if($role->name === 'System Administrator') Full system access, user & data management
                        @elseif($role->name === 'Farmmantra Staff') Order approval, inventory monitoring
                        @elseif($role->name === 'Finance Department') Payment verification & reconciliation
                        @elseif($role->name === 'Franchise Partner') Orders, sales, inventory, payments
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection