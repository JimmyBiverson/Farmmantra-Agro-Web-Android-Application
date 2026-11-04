@extends('layouts.app')
@section('title', 'Notification Settings')
@section('page-title', 'Notification Settings')

@php
    $settingsNav = [
        ['route' => 'web.admin.settings.site', 'label' => 'Site Identity', 'icon' => 'fa-palette'],
        ['route' => 'web.admin.settings.general', 'label' => 'General', 'icon' => 'fa-cog'],
        ['route' => 'web.admin.settings.users', 'label' => 'User Management', 'icon' => 'fa-user-gear'],
        ['route' => 'web.admin.settings.roles', 'label' => 'Roles & Permissions', 'icon' => 'fa-shield-halved'],
        ['route' => 'web.admin.settings.notifications', 'label' => 'Notifications', 'icon' => 'fa-bell'],
        ['route' => 'web.admin.settings.system', 'label' => 'System Info', 'icon' => 'fa-server'],
    ];
    $s = \App\Models\Setting::whereIn('group_name', ['notifications'])->pluck('value', 'key')->toArray();
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

<form action="{{ route('web.admin.settings.notifications.update') }}" method="POST">
    @csrf
    <div class="space-y-6" x-data="{
        notif_email_new_order: {{ ($s['notif_email_new_order'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_email_order_status: {{ ($s['notif_email_order_status'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_email_payment_submitted: {{ ($s['notif_email_payment_submitted'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_email_payment_verified: {{ ($s['notif_email_payment_verified'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_email_low_stock: {{ ($s['notif_email_low_stock'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_email_new_user: {{ ($s['notif_email_new_user'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_email_franchise_deactivated: {{ ($s['notif_email_franchise_deactivated'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_inapp_badge_counts: {{ ($s['notif_inapp_badge_counts'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_inapp_toasts: {{ ($s['notif_inapp_toasts'] ?? '1') === '1' ? 'true' : 'false' }},
        notif_inapp_auto_refresh: {{ ($s['notif_inapp_auto_refresh'] ?? '1') === '1' ? 'true' : 'false' }}
    }">

        {{-- Email Notifications --}}
        <div class="card-full">
            <div class="card-header">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl gradient-indigo flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-envelope text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Email Notifications</h3>
                        <p class="text-xs" style="color:var(--text-muted)">Choose which events trigger email alerts to the relevant team</p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="space-y-2">
                    @php
                    $emailToggles = [
                        ['key' => 'notif_email_new_order', 'icon' => 'fa-clipboard-list', 'color' => 'gradient-amber', 'label' => 'New order placed', 'desc' => 'Notify admin when a franchise partner places a new order', 'role' => 'Admin'],
                        ['key' => 'notif_email_order_status', 'icon' => 'fa-arrows-rotate', 'color' => 'gradient-purple', 'label' => 'Order approved / declined', 'desc' => 'Notify franchise partner when their order status changes', 'role' => 'Franchise'],
                        ['key' => 'notif_email_payment_submitted', 'icon' => 'fa-money-bill-wave', 'color' => 'gradient-green', 'label' => 'Payment submitted', 'desc' => 'Notify finance team when proof of payment is uploaded', 'role' => 'Finance'],
                        ['key' => 'notif_email_payment_verified', 'icon' => 'fa-circle-check', 'color' => 'gradient-green', 'label' => 'Payment verified', 'desc' => 'Notify franchise when payment is accepted or rejected', 'role' => 'Franchise'],
                        ['key' => 'notif_email_low_stock', 'icon' => 'fa-box-open', 'color' => 'gradient-rose', 'label' => 'Low stock alert', 'desc' => 'Alert staff when warehouse inventory drops below reorder level', 'role' => 'Staff'],
                        ['key' => 'notif_email_new_user', 'icon' => 'fa-user-plus', 'color' => 'gradient-cyan', 'label' => 'New user registered', 'desc' => 'Notify admin when a new user account is created', 'role' => 'Admin'],
                        ['key' => 'notif_email_franchise_deactivated', 'icon' => 'fa-store-slash', 'color' => 'gradient-rose', 'label' => 'Franchise deactivated', 'desc' => 'Notify admin when a franchise account is deactivated', 'role' => 'Admin'],
                    ];
                    @endphp
                    @foreach($emailToggles as $t)
                    <div class="notification-row flex items-center justify-between rounded-2xl p-4 border-2 transition-all duration-300 hover:shadow-lg group"
                         :style="{{ $t['key'] }}
                             ? 'border-color:rgba(99,102,241,0.25); background:linear-gradient(135deg, rgba(99,102,241,0.04), rgba(139,92,246,0.04))'
                             : 'border-color:var(--border-color); background:var(--bg-card)'">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-10 w-10 rounded-xl {{ $t['color'] }} flex items-center justify-center flex-shrink-0 transition-all duration-300"
                                 :style="{{ $t['key'] }} ? 'box-shadow:0 4px 12px rgba(99,102,241,0.3); transform:scale(1.05)' : 'box-shadow:none; transform:scale(1)'">
                                <i class="fas {{ $t['icon'] }} text-white text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ $t['label'] }}</p>
                                    <span class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider"
                                          :style="{{ $t['key'] }}
                                              ? 'background:rgba(99,102,241,0.12); color:#818cf8'
                                              : 'background:rgba(148,163,184,0.1); color:var(--text-muted)'">{{ $t['role'] }}</span>
                                </div>
                                <p class="text-xs mt-0.5 leading-relaxed" style="color:var(--text-muted)">{{ $t['desc'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0 ml-4 pl-4 border-l"
                             :style="{{ $t['key'] }} ? 'border-color:rgba(99,102,241,0.2)' : 'border-color:var(--border-color)'">
                            <span class="text-[10px] font-bold uppercase tracking-wider min-w-[28px] text-center"
                                  :style="{{ $t['key'] }} ? 'color:#818cf8' : 'color:var(--text-muted)'"
                                  x-text="{{ $t['key'] }} ? 'ON' : 'OFF'"></span>
                            <button type="button" @click="{{ $t['key'] }} = !{{ $t['key'] }}"
                                    class="relative flex-shrink-0 w-14 h-7 rounded-full transition-all duration-300 focus:outline-none overflow-hidden"
                                    :style="{{ $t['key'] }}
                                        ? 'background:linear-gradient(135deg,#6366f1,#8b5cf6); border:2px solid rgba(139,92,246,0.5); box-shadow:0 0 16px rgba(99,102,241,0.35), inset 0 1px 0 rgba(255,255,255,0.15)'
                                        : 'background:var(--bg-input); border:2px solid var(--border-color); box-shadow:inset 0 2px 4px rgba(0,0,0,0.06)'"
                                role="switch" :aria-checked="{{ $t['key'] }}">
                                <span class="absolute top-[3px] left-[3px] w-5 h-5 rounded-full shadow-lg transition-all duration-300 flex items-center justify-center"
                                      :style="{{ $t['key'] }}
                                          ? 'transform:translateX(24px); background:white; box-shadow:0 2px 8px rgba(99,102,241,0.4)'
                                          : 'transform:translateX(0); background:var(--text-muted); box-shadow:0 1px 3px rgba(0,0,0,0.15)'">
                                    <i class="fas fa-check transition-all duration-300"
                                       :style="{{ $t['key'] }} ? 'opacity:1; transform:scale(1); color:#6366f1' : 'opacity:0; transform:scale(0.5); color:var(--text-muted)'" style="font-size:8px"></i>
                                </span>
                            </button>
                            <input type="hidden" name="{{ $t['key'] }}" :value="{{ $t['key'] }} ? '1' : '0'">
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- In-App Notifications --}}
        <div class="card-full">
            <div class="card-header">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl gradient-purple flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-bell text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">In-App Notifications</h3>
                        <p class="text-xs" style="color:var(--text-muted)">Control how notifications appear inside the dashboard</p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="space-y-2">
                    @php
                    $inAppToggles = [
                        ['key' => 'notif_inapp_badge_counts', 'icon' => 'fa-circle-dot', 'color' => 'gradient-amber', 'label' => 'Show badge counts on sidebar', 'desc' => 'Display pending order and payment counts as red badges in the navigation sidebar'],
                        ['key' => 'notif_inapp_toasts', 'icon' => 'fa-comment-dots', 'color' => 'gradient-cyan', 'label' => 'Show toast notifications', 'desc' => 'Display brief popup notifications in the corner of the dashboard when events occur'],
                        ['key' => 'notif_inapp_auto_refresh', 'icon' => 'fa-rotate', 'color' => 'gradient-green', 'label' => 'Auto-refresh dashboard', 'desc' => 'Periodically refresh dashboard data every 60 seconds for real-time updates'],
                    ];
                    @endphp
                    @foreach($inAppToggles as $t)
                    <div class="notification-row flex items-center justify-between rounded-2xl p-4 border-2 transition-all duration-300 hover:shadow-lg group"
                         :style="{{ $t['key'] }}
                             ? 'border-color:rgba(99,102,241,0.25); background:linear-gradient(135deg, rgba(99,102,241,0.04), rgba(139,92,246,0.04))'
                             : 'border-color:var(--border-color); background:var(--bg-card)'">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-10 w-10 rounded-xl {{ $t['color'] }} flex items-center justify-center flex-shrink-0 transition-all duration-300"
                                 :style="{{ $t['key'] }} ? 'box-shadow:0 4px 12px rgba(99,102,241,0.3); transform:scale(1.05)' : 'box-shadow:none; transform:scale(1)'">
                                <i class="fas {{ $t['icon'] }} text-white text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ $t['label'] }}</p>
                                <p class="text-xs mt-0.5 leading-relaxed" style="color:var(--text-muted)">{{ $t['desc'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0 ml-4 pl-4 border-l"
                             :style="{{ $t['key'] }} ? 'border-color:rgba(99,102,241,0.2)' : 'border-color:var(--border-color)'">
                            <span class="text-[10px] font-bold uppercase tracking-wider min-w-[28px] text-center"
                                  :style="{{ $t['key'] }} ? 'color:#818cf8' : 'color:var(--text-muted)'"
                                  x-text="{{ $t['key'] }} ? 'ON' : 'OFF'"></span>
                            <button type="button" @click="{{ $t['key'] }} = !{{ $t['key'] }}"
                                    class="relative flex-shrink-0 w-14 h-7 rounded-full transition-all duration-300 focus:outline-none overflow-hidden"
                                    :style="{{ $t['key'] }}
                                        ? 'background:linear-gradient(135deg,#6366f1,#8b5cf6); border:2px solid rgba(139,92,246,0.5); box-shadow:0 0 16px rgba(99,102,241,0.35), inset 0 1px 0 rgba(255,255,255,0.15)'
                                        : 'background:var(--bg-input); border:2px solid var(--border-color); box-shadow:inset 0 2px 4px rgba(0,0,0,0.06)'"
                                role="switch" :aria-checked="{{ $t['key'] }}">
                                <span class="absolute top-[3px] left-[3px] w-5 h-5 rounded-full shadow-lg transition-all duration-300 flex items-center justify-center"
                                      :style="{{ $t['key'] }}
                                          ? 'transform:translateX(24px); background:white; box-shadow:0 2px 8px rgba(99,102,241,0.4)'
                                          : 'transform:translateX(0); background:var(--text-muted); box-shadow:0 1px 3px rgba(0,0,0,0.15)'">
                                    <i class="fas fa-check transition-all duration-300"
                                       :style="{{ $t['key'] }} ? 'opacity:1; transform:scale(1); color:#6366f1' : 'opacity:0; transform:scale(0.5); color:var(--text-muted)'" style="font-size:8px"></i>
                                </span>
                            </button>
                            <input type="hidden" name="{{ $t['key'] }}" :value="{{ $t['key'] }} ? '1' : '0'">
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Notification Recipients --}}
        <div class="card-full">
            <div class="card-header">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl gradient-green flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-at text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Notification Recipients</h3>
                        <p class="text-xs" style="color:var(--text-muted)">Email addresses that receive notification summaries</p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)"><i class="fas fa-user-shield mr-1"></i> Admin notification email</label>
                        <input type="email" name="notif_admin_email" value="{{ $s['notif_admin_email'] ?? 'admin@farmmantra.co.ug' }}"
                               class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)"><i class="fas fa-calculator mr-1"></i> Finance notification email</label>
                        <input type="email" name="notif_finance_email" value="{{ $s['notif_finance_email'] ?? 'finance@farmmantra.co.ug' }}"
                               class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="card-full">
            <div class="card-body">
                <div class="flex items-center gap-3 p-4 rounded-xl" style="background:rgba(99,102,241,0.05); border:1px solid rgba(99,102,241,0.15)">
                    <i class="fas fa-lightbulb text-lg" style="color:var(--accent)"></i>
                    <div>
                        <p class="text-xs font-semibold" style="color:var(--text-primary)">Tip</p>
                        <p class="text-[10px]" style="color:var(--text-muted)">Toggled-off notifications are silenced immediately. Toggle them back on anytime to resume alerts.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Save --}}
        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all" style="background:linear-gradient(135deg,#6366f1,#8b5cf6); box-shadow:0 4px 15px rgba(99,102,241,0.4)" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                <i class="fas fa-save mr-2"></i> Save Settings
            </button>
        </div>
    </div>
</form>
@endsection
