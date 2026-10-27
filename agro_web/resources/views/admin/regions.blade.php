@extends('layouts.app')
@section('title', 'Regions & Districts')
@section('page-title', 'Regions & Districts')

@section('content')
@include('partials.fm-ui')
<div class="fm-grid-2 mb-6">
    <form method="POST" action="{{ route('web.admin.regions.store') }}" class="fm-panel fm-stack">
        @csrf
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">New business region</h3>
        <input class="fm-input" name="name" required maxlength="100" placeholder="e.g. Gulu Region">
        <input class="fm-input" name="description" maxlength="255" placeholder="Description (optional)">
        <button class="fm-btn" type="submit"><i class="fas fa-plus"></i> Create region</button>
        <p class="fm-muted">Then open the region below to choose its districts and the franchise that serves it.</p>
    </form>
    <form method="POST" action="{{ route('web.admin.districts.store') }}" class="fm-panel fm-stack">
        @csrf
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Add a district</h3>
        <p class="fm-muted">All {{ $districts->count() }} districts of Uganda are already loaded. Add a newly created district here.</p>
        <input class="fm-input" name="name" required maxlength="100" placeholder="District name">
        <div class="fm-row">
            <select class="fm-select" name="official_region" style="flex:1"><option value="">Official region…</option>@foreach(['Central', 'Eastern', 'Northern', 'Western'] as $o)<option>{{ $o }}</option>@endforeach</select>
            <select class="fm-select" name="region_id" style="flex:1"><option value="">Business region…</option>@foreach($regions as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
        </div>
        <button class="fm-btn fm-btn-ghost" type="submit">Add district</button>
    </form>
</div>

@php $unassigned = $districts->whereNull('region_id'); @endphp
@if($unassigned->count())
<div class="fm-panel mb-4" style="border-color:#d97706"><strong style="color:#d97706">{{ $unassigned->count() }} district(s) have no region:</strong> <span class="fm-muted">{{ $unassigned->pluck('name')->implode(', ') }}</span></div>
@endif

<div class="fm-stack">
@foreach($regions as $region)
<details class="fm-panel" @if($loop->first && $regions->count() === 1) open @endif>
    <summary style="cursor:pointer; display:flex; gap:.75rem; align-items:center">
        <strong style="color:var(--text-primary)">{{ $region->name }}</strong>
        <span class="badge badge-info">{{ $region->districts_count }} districts</span>
        <span class="fm-muted">Franchise: {{ $region->franchises->pluck('name')->implode(', ') ?: 'none' }}</span>
    </summary>
    <form method="POST" action="{{ route('web.admin.regions.update', $region) }}" class="fm-stack mt-3" x-data="{ filter: '' }">
        @csrf
        <div class="fm-row">
            <div style="flex:1"><label class="fm-label">Name</label><input class="fm-input" name="name" value="{{ $region->name }}" required></div>
            <div style="flex:2"><label class="fm-label">Description</label><input class="fm-input" name="description" value="{{ $region->description }}"></div>
        </div>
        <div>
            <label class="fm-label">Franchise(s) serving this region</label>
            <div class="fm-row">
                @foreach($franchises as $f)
                <label class="fm-check" style="min-width:12rem"><input type="checkbox" name="franchise_ids[]" value="{{ $f->id }}" @checked($f->region_id === $region->id)> {{ $f->name }}</label>
                @endforeach
            </div>
        </div>
        <div>
            <div class="fm-row"><label class="fm-label" style="margin:0">Districts in this region</label><input class="fm-input" x-model="filter" placeholder="Filter districts…" style="max-width:14rem; margin-left:auto"></div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(11rem, 1fr)); gap:.3rem; max-height:18rem; overflow-y:auto; margin-top:.5rem">
                @foreach($districts as $d)
                <label class="fm-check" x-show="'{{ strtolower($d->name) }}'.includes(filter.toLowerCase())" style="font-size:.8rem">
                    <input type="checkbox" name="district_ids[]" value="{{ $d->id }}" @checked($d->region_id === $region->id)>
                    <span>{{ $d->name }} @if($d->region_id && $d->region_id !== $region->id)<span class="fm-muted">({{ $d->region?->name }})</span>@endif</span>
                </label>
                @endforeach
            </div>
            <p class="fm-muted mt-1">Ticking a district that belongs to another region moves it here.</p>
        </div>
        <button class="fm-btn" type="submit"><i class="fas fa-save"></i> Save region</button>
    </form>
</details>
@endforeach
</div>
@endsection
