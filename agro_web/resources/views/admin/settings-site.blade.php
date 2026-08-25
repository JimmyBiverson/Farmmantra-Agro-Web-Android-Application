@extends('layouts.app')
@section('title', 'Site Identity')
@section('page-title', 'Site Identity')

@php
    $settingsNav = [
        ['route' => 'web.admin.settings.site', 'label' => 'Site Identity', 'icon' => 'fa-palette'],
        ['route' => 'web.admin.settings.general', 'label' => 'General', 'icon' => 'fa-cog'],
        ['route' => 'web.admin.settings.users', 'label' => 'User Management', 'icon' => 'fa-user-gear'],
        ['route' => 'web.admin.settings.roles', 'label' => 'Roles & Permissions', 'icon' => 'fa-shield-halved'],
        ['route' => 'web.admin.settings.notifications', 'label' => 'Notifications', 'icon' => 'fa-bell'],
        ['route' => 'web.admin.settings.system', 'label' => 'System Info', 'icon' => 'fa-server'],
    ];
    $hasLogo = !empty($site['site_logo_url']);
    $hasFavicon = !empty($site['site_favicon_url']);
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

<form action="{{ route('web.admin.settings.site.update') }}" method="POST" enctype="multipart/form-data" id="siteIdentityForm">
    @csrf
    <div class="space-y-6">

        {{-- Branding Section --}}
        <div class="card-full" x-data="siteIdentity()">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Branding</h3>
                    <p class="text-xs" style="color:var(--text-muted)">Upload your site logo, favicon, and social sharing image</p>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- Site Logo --}}
                    <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-card)">
                        <label class="block text-xs font-semibold mb-3" style="color:var(--text-secondary)">Site Logo</label>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="h-14 w-20 rounded-lg overflow-hidden border flex-shrink-0" style="border-color:var(--border-color); background:var(--bg-input)">
                                @if($hasLogo)
                                <img src="{{ $site['site_logo_url'] }}" alt="Logo" style="width:100%;height:100%;object-fit:contain;padding:0.25rem">
                                @else
                                <div class="h-full w-full gradient-indigo flex items-center justify-center">
                                    <span class="text-white font-bold text-sm">FM</span>
                                </div>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs mb-0.5" style="color:var(--text-muted)">Current logo</p>
                                <p class="text-[10px]" style="color:var(--text-secondary)">200×60px · PNG/SVG/WebP</p>
                            </div>
                        </div>
                        <label class="block cursor-pointer">
                            <div class="w-full rounded-lg border-2 border-dashed p-4 text-center transition-all hover:border-indigo-400"
                                 style="border-color:var(--border-color)" :style="logoPreview ? 'border-color:#6366f1' : ''">
                                <template x-if="!logoPreview">
                                    <div>
                                        <i class="fas fa-cloud-arrow-up text-lg mb-1" style="color:var(--text-muted)"></i>
                                        <p class="text-[10px] font-medium" style="color:var(--text-muted)">Click to upload logo</p>
                                    </div>
                                </template>
                                <template x-if="logoPreview">
                                    <div class="flex items-center justify-center gap-2">
                                        <i class="fas fa-check-circle text-emerald-400 text-sm"></i>
                                        <p class="text-[10px] font-medium text-emerald-400" x-text="logoName"></p>
                                    </div>
                                </template>
                            </div>
                            <input type="file" name="site_logo" accept="image/png,jpeg,jpg,webp,svg" class="hidden" @change="logoPreview = URL.createObjectURL(event.target.files[0]); logoName = event.target.files[0].name">
                        </label>
                    </div>

                    {{-- Favicon --}}
                    <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-card)">
                        <label class="block text-xs font-semibold mb-3" style="color:var(--text-secondary)">Favicon</label>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="h-10 w-10 rounded-lg overflow-hidden border flex-shrink-0" style="border-color:var(--border-color); background:var(--bg-input)">
                                @if($hasFavicon)
                                <img src="{{ $site['site_favicon_url'] }}" alt="Favicon" style="width:100%;height:100%;object-fit:contain;padding:0.25rem">
                                @else
                                <div class="h-full w-full gradient-indigo flex items-center justify-center">
                                    <span class="text-white font-bold text-[10px]">FM</span>
                                </div>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs mb-0.5" style="color:var(--text-muted)">Current favicon</p>
                                <p class="text-[10px]" style="color:var(--text-secondary)">32×32px · ICO/PNG</p>
                            </div>
                        </div>
                        <label class="block cursor-pointer">
                            <div class="w-full rounded-lg border-2 border-dashed p-4 text-center transition-all hover:border-indigo-400"
                                 style="border-color:var(--border-color)" :style="faviconPreview ? 'border-color:#6366f1' : ''">
                                <template x-if="!faviconPreview">
                                    <div>
                                        <i class="fas fa-cloud-arrow-up text-lg mb-1" style="color:var(--text-muted)"></i>
                                        <p class="text-[10px] font-medium" style="color:var(--text-muted)">Click to upload favicon</p>
                                    </div>
                                </template>
                                <template x-if="faviconPreview">
                                    <div class="flex items-center justify-center gap-2">
                                        <i class="fas fa-check-circle text-emerald-400 text-sm"></i>
                                        <p class="text-[10px] font-medium text-emerald-400" x-text="faviconName"></p>
                                    </div>
                                </template>
                            </div>
                            <input type="file" name="site_favicon" accept="image/x-icon,image/png,image/jpeg,image/webp" class="hidden" @change="faviconPreview = URL.createObjectURL(event.target.files[0]); faviconName = event.target.files[0].name">
                        </label>
                    </div>

                    {{-- OG / Social Sharing Image --}}
                    <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-card)">
                        <label class="block text-xs font-semibold mb-3" style="color:var(--text-secondary)">Social Sharing Image</label>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="h-14 w-20 rounded-lg overflow-hidden border flex-shrink-0" style="border-color:var(--border-color); background:var(--bg-input)">
                                @if(!empty($site['og_image_url']))
                                <img src="{{ $site['og_image_url'] }}" alt="OG" style="width:100%;height:100%;object-fit:cover">
                                @else
                                <div class="h-full w-full gradient-purple flex items-center justify-center">
                                    <i class="fas fa-share-nodes text-white text-sm"></i>
                                </div>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs mb-0.5" style="color:var(--text-muted)">Current image</p>
                                <p class="text-[10px]" style="color:var(--text-secondary)">1200×630px · JPG/PNG</p>
                            </div>
                        </div>
                        <label class="block cursor-pointer">
                            <div class="w-full rounded-lg border-2 border-dashed p-4 text-center transition-all hover:border-indigo-400"
                                 style="border-color:var(--border-color)" :style="ogPreview ? 'border-color:#6366f1' : ''">
                                <template x-if="!ogPreview">
                                    <div>
                                        <i class="fas fa-cloud-arrow-up text-lg mb-1" style="color:var(--text-muted)"></i>
                                        <p class="text-[10px] font-medium" style="color:var(--text-muted)">Click to upload OG image</p>
                                    </div>
                                </template>
                                <template x-if="ogPreview">
                                    <div class="flex items-center justify-center gap-2">
                                        <i class="fas fa-check-circle text-emerald-400 text-sm"></i>
                                        <p class="text-[10px] font-medium text-emerald-400" x-text="ogName"></p>
                                    </div>
                                </template>
                            </div>
                            <input type="file" name="og_image" accept="image/png,image/jpeg,image/webp" class="hidden" @change="ogPreview = URL.createObjectURL(event.target.files[0]); ogName = event.target.files[0].name">
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Site Name + Tagline --}}
        <div class="card-full">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Site Details</h3>
                    <p class="text-xs" style="color:var(--text-muted)">The name and tagline shown across the site and in browser tabs</p>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Site Name</label>
                        <input type="text" name="site_name" value="{{ old('site_name', $site['site_name'] ?? 'Farmmantra') }}"
                               class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)">Site Tagline</label>
                        <input type="text" name="site_tagline" value="{{ old('site_tagline', $site['site_tagline'] ?? 'Agro Chemicals Limited') }}"
                               class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                </div>
            </div>
        </div>

        {{-- Theme Colors --}}
        <div class="card-full" x-data="themeColors()">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Theme Colors</h3>
                    <p class="text-xs" style="color:var(--text-muted)">Customize the accent and status colors across the entire site</p>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {{-- Accent --}}
                    <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-card)">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center" :style="'background:' + accent">
                                <i class="fas fa-palette text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">Primary / Accent</p>
                                <p class="text-[10px]" style="color:var(--text-muted)">Buttons, links, sidebar active state</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="color" x-model="accent" name="theme_accent" class="w-10 h-10 rounded-lg cursor-pointer border-0 p-0">
                            <input type="text" x-model="accent" class="flex-1 rounded-lg border px-3 py-2 text-xs font-mono" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        </div>
                        <div class="flex gap-1.5 mt-3">
                            <template x-for="c in presetAccents" :key="c">
                                <button type="button" @click="accent = c" class="h-6 w-6 rounded-full border-2 transition hover:scale-110" :style="'background:' + c" :class="accent === c ? 'border-white scale-110' : 'border-transparent'"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Success --}}
                    <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-card)">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center" :style="'background:' + success">
                                <i class="fas fa-check-circle text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">Success</p>
                                <p class="text-[10px]" style="color:var(--text-muted)">Completed actions, active badges</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="color" x-model="success" name="theme_success" class="w-10 h-10 rounded-lg cursor-pointer border-0 p-0">
                            <input type="text" x-model="success" class="flex-1 rounded-lg border px-3 py-2 text-xs font-mono" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        </div>
                        <div class="flex gap-1.5 mt-3">
                            <template x-for="c in presetSuccess" :key="c">
                                <button type="button" @click="success = c" class="h-6 w-6 rounded-full border-2 transition hover:scale-110" :style="'background:' + c" :class="success === c ? 'border-white scale-110' : 'border-transparent'"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Warning --}}
                    <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-card)">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center" :style="'background:' + warning">
                                <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">Warning</p>
                                <p class="text-[10px]" style="color:var(--text-muted)">Pending states, low stock alerts</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="color" x-model="warning" name="theme_warning" class="w-10 h-10 rounded-lg cursor-pointer border-0 p-0">
                            <input type="text" x-model="warning" class="flex-1 rounded-lg border px-3 py-2 text-xs font-mono" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        </div>
                        <div class="flex gap-1.5 mt-3">
                            <template x-for="c in presetWarning" :key="c">
                                <button type="button" @click="warning = c" class="h-6 w-6 rounded-full border-2 transition hover:scale-110" :style="'background:' + c" :class="warning === c ? 'border-white scale-110' : 'border-transparent'"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Danger --}}
                    <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-card)">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center" :style="'background:' + danger">
                                <i class="fas fa-circle-xmark text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">Danger</p>
                                <p class="text-[10px]" style="color:var(--text-muted)">Errors, declined, destructive actions</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="color" x-model="danger" name="theme_danger" class="w-10 h-10 rounded-lg cursor-pointer border-0 p-0">
                            <input type="text" x-model="danger" class="flex-1 rounded-lg border px-3 py-2 text-xs font-mono" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        </div>
                        <div class="flex gap-1.5 mt-3">
                            <template x-for="c in presetDanger" :key="c">
                                <button type="button" @click="danger = c" class="h-6 w-6 rounded-full border-2 transition hover:scale-110" :style="'background:' + c" :class="danger === c ? 'border-white scale-110' : 'border-transparent'"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Info --}}
                    <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-card)">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center" :style="'background:' + info">
                                <i class="fas fa-circle-info text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">Info</p>
                                <p class="text-[10px]" style="color:var(--text-muted)">Informational badges, tooltips</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="color" x-model="info" name="theme_info" class="w-10 h-10 rounded-lg cursor-pointer border-0 p-0">
                            <input type="text" x-model="info" class="flex-1 rounded-lg border px-3 py-2 text-xs font-mono" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        </div>
                        <div class="flex gap-1.5 mt-3">
                            <template x-for="c in presetInfo" :key="c">
                                <button type="button" @click="info = c" class="h-6 w-6 rounded-full border-2 transition hover:scale-110" :style="'background:' + c" :class="info === c ? 'border-white scale-110' : 'border-transparent'"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Reset --}}
                    <div class="rounded-xl p-5 border flex flex-col items-center justify-center" style="border-color:var(--border-color); background:var(--bg-card)">
                        <i class="fas fa-rotate-left text-xl mb-2" style="color:var(--text-muted)"></i>
                        <p class="text-xs font-semibold mb-3" style="color:var(--text-primary)">Reset to Defaults</p>
                        <button type="button" @click="accent='#6366f1'; success='#10b981'; warning='#f59e0b'; danger='#ef4444'; info='#06b6d4'"
                                class="px-4 py-2 rounded-lg text-xs font-semibold border transition hover:bg-red-500/10 hover:text-red-500 hover:border-red-300"
                                style="border-color:var(--border-color); color:var(--text-secondary)">
                            <i class="fas fa-rotate-left mr-1"></i> Reset All
                        </button>
                    </div>
                </div>

                {{-- Live Preview --}}
                <div class="mt-6 rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-input)">
                    <p class="text-xs font-semibold mb-3" style="color:var(--text-muted)">Live Preview</p>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" :style="'background:' + accent">Accent Button</span>
                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" :style="'background:' + success">Success</span>
                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" :style="'background:' + warning">Warning</span>
                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" :style="'background:' + danger">Danger</span>
                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" :style="'background:' + info">Info</span>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold border-2" :style="'border-color:' + accent + '; color:' + accent">Outline</span>
                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold" :style="'background:' + accent + '14; color:' + accent">Soft</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="card-full">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Contact Information</h3>
                    <p class="text-xs" style="color:var(--text-muted)">Displayed on the site footer and shared publicly</p>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)"><i class="fas fa-phone mr-1"></i> Phone</label>
                        <input type="tel" name="site_phone" value="{{ old('site_phone', $site['site_phone'] ?? '') }}" placeholder="+256 700 000000"
                               class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)"><i class="fas fa-envelope mr-1"></i> Email</label>
                        <input type="email" name="site_email" value="{{ old('site_email', $site['site_email'] ?? '') }}" placeholder="info@farmmantra.co.ug"
                               class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color:var(--text-secondary)"><i class="fas fa-location-dot mr-1"></i> Address</label>
                        <input type="text" name="site_address" value="{{ old('site_address', $site['site_address'] ?? '') }}" placeholder="Kampala, Uganda"
                               class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                    </div>
                </div>
            </div>
        </div>

        {{-- Live Preview --}}
        <div class="card-full">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Live Preview</h3>
                    <p class="text-xs" style="color:var(--text-muted)">How your site name and favicon appear in the browser</p>
                </div>
            </div>
            <div class="card-body">
                <div class="rounded-xl p-5 border" style="border-color:var(--border-color); background:var(--bg-input)">
                    {{-- Browser Tab --}}
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center gap-1.5">
                            <div class="h-2.5 w-2.5 rounded-full bg-red-400"></div>
                            <div class="h-2.5 w-2.5 rounded-full bg-amber-400"></div>
                            <div class="h-2.5 w-2.5 rounded-full bg-emerald-400"></div>
                        </div>
                        <div class="flex-1 flex items-center gap-2 rounded-lg px-3 py-1.5" style="background:var(--bg-card-solid); border:1px solid var(--border-color)">
                            <div class="h-3 w-3 rounded-sm overflow-hidden gradient-indigo flex items-center justify-center flex-shrink-0">
                                @if(!empty($site['site_favicon_url']))
                                <img src="{{ $site['site_favicon_url'] }}" alt="" class="h-full w-full object-contain">
                                @else
                                <span class="text-white text-[6px] font-bold">FM</span>
                                @endif
                            </div>
                            <span class="text-xs font-medium truncate" style="color:var(--text-primary)">{{ $site['site_name'] ?? 'Farmmantra' }} — {{ $site['site_tagline'] ?? 'Agro Chemicals Limited' }}</span>
                            <i class="fas fa-lock text-[8px] ml-auto flex-shrink-0" style="color:var(--text-muted)"></i>
                        </div>
                    </div>
                    {{-- Link Preview Card --}}
                    <div class="rounded-lg overflow-hidden border" style="border-color:var(--border-color); background:var(--bg-card-solid)">
                        <div class="h-24 w-full gradient-indigo flex items-center justify-center relative overflow-hidden">
                            @if(!empty($site['og_image_url']))
                            <img src="{{ $site['og_image_url'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                            @elseif(!empty($site['site_logo_url']))
                            <img src="{{ $site['site_logo_url'] }}" alt="" class="absolute inset-0 h-full w-full object-contain p-5">
                            @else
                            <div class="absolute inset-0 opacity-20" style="background:linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%)"></div>
                            <span class="text-white font-bold text-xl relative z-10">{{ $site['site_name'] ?? 'Farmmantra' }}</span>
                            @endif
                        </div>
                        <div class="p-3">
                            <p class="text-xs font-semibold mb-0.5 truncate" style="color:var(--text-primary)">{{ $site['site_name'] ?? 'Farmmantra' }} — {{ $site['site_tagline'] ?? 'Agro Chemicals Limited' }}</p>
                            <p class="text-[10px] truncate" style="color:var(--text-muted)">{{ url('/') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Save --}}
        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all" style="background:linear-gradient(135deg,#6366f1,#8b5cf6); box-shadow:0 4px 15px rgba(99,102,241,0.4)" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                <i class="fas fa-save mr-2"></i> Save Identity
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function siteIdentity() {
        return {
            logoPreview: null,
            logoName: '',
            faviconPreview: null,
            faviconName: '',
            ogPreview: null,
            ogName: ''
        }
    }
    function themeColors() {
        return {
            accent: '{{ $site["theme_accent"] ?? "#6366f1" }}',
            success: '{{ $site["theme_success"] ?? "#10b981" }}',
            warning: '{{ $site["theme_warning"] ?? "#f59e0b" }}',
            danger: '{{ $site["theme_danger"] ?? "#ef4444" }}',
            info: '{{ $site["theme_info"] ?? "#06b6d4" }}',
            presetAccents: ['#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#eab308','#10b981','#06b6d4','#3b82f6','#0f172a'],
            presetSuccess: ['#10b981','#059669','#16a34a','#22c55e','#34d399'],
            presetWarning: ['#f59e0b','#d97706','#f97316','#eab308','#fbbf24'],
            presetDanger: ['#ef4444','#dc2626','#f43f5e','#e11d48','#fb7185'],
            presetInfo: ['#06b6d4','#0891b2','#3b82f6','#6366f1','#8b5cf6']
        }
    }
</script>
@endpush
