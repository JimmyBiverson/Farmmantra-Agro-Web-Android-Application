@extends('layouts.app')
@section('title', 'Customers')
@section('page-title', 'Customers — Retailers & Farmers')

@section('content')
@include('partials.fm-ui')
<div class="fm-grid-2 mb-6">
    <div class="fm-panel">
        <h3 class="text-sm font-semibold mb-3" style="color:var(--text-primary)">Register a customer</h3>
        <form method="POST" action="{{ route('web.admin.customers.store') }}" class="fm-stack">
            @csrf
            <div class="fm-row">
                <div style="flex:2"><label class="fm-label">Name</label><input class="fm-input" name="name" required value="{{ old('name') }}"></div>
                <div style="flex:1"><label class="fm-label">Type</label>
                    <select class="fm-select" name="customer_type" required>
                        @foreach(\App\Models\Customer::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('customer_type') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="fm-row">
                <div style="flex:1"><label class="fm-label">District</label>
                    <select class="fm-select" name="district_id" required>
                        <option value="">Select district…</option>
                        @foreach($districts as $d)<option value="{{ $d->id }}" @selected(old('district_id') == $d->id)>{{ $d->name }} — {{ $d->region?->name ?? 'no region' }}</option>@endforeach
                    </select>
                </div>
                <div style="flex:1"><label class="fm-label">Serving franchise</label>
                    <select class="fm-select" name="franchise_id">
                        <option value="">Automatic (franchise of the district's region)</option>
                        @foreach($franchises as $f)<option value="{{ $f->id }}" @selected(old('franchise_id') == $f->id)>{{ $f->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="fm-row">
                <div style="flex:1"><label class="fm-label">Phone</label><input class="fm-input" name="phone" value="{{ old('phone') }}" placeholder="07XX XXX XXX"></div>
                <div style="flex:1"><label class="fm-label">Email</label><input class="fm-input" type="email" name="email" value="{{ old('email') }}"></div>
            </div>
            <div><label class="fm-label">Address / village</label><input class="fm-input" name="address" value="{{ old('address') }}"></div>
            <button class="fm-btn" type="submit"><i class="fas fa-user-plus"></i> Save customer</button>
        </form>
    </div>
    <form method="GET" class="fm-panel fm-stack">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Find customers</h3>
        <input class="fm-input" name="q" value="{{ request('q') }}" placeholder="Name or phone">
        <div class="fm-row">
            <select class="fm-select" name="customer_type" style="flex:1"><option value="">All types</option>@foreach(\App\Models\Customer::TYPES as $v => $l)<option value="{{ $v }}" @selected(request('customer_type') === $v)>{{ $l }}</option>@endforeach</select>
            <select class="fm-select" name="region_id" style="flex:1"><option value="">All regions</option>@foreach($regions as $r)<option value="{{ $r->id }}" @selected((string) request('region_id') === (string) $r->id)>{{ $r->name }}</option>@endforeach</select>
        </div>
        <select class="fm-select" name="franchise_id"><option value="">All franchises</option>@foreach($franchises as $f)<option value="{{ $f->id }}" @selected((string) request('franchise_id') === (string) $f->id)>{{ $f->name }}</option>@endforeach</select>
        <div class="fm-row"><button class="fm-btn" type="submit"><i class="fas fa-filter"></i> Filter</button><a class="fm-btn fm-btn-ghost" href="{{ route('web.admin.customers') }}">Reset</a></div>
        <p class="fm-muted">A franchise sees the customers assigned to it plus all customers in the districts of its region. Manage regions under <a href="{{ route('web.admin.regions') }}" style="color:var(--accent)">Regions & Districts</a>.</p>
    </form>
</div>

<div class="card-full">
    <div class="card-header"><h3 class="text-sm font-semibold" style="color:var(--text-primary)">Customers ({{ $customers->total() }})</h3></div>
    <div class="overflow-x-auto">
    <table class="fm-table">
        <thead><tr><th>Name</th><th>Type</th><th>District / Region</th><th>Franchise</th><th>Phone</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        @forelse($customers as $c)
        <tr>
            <td style="color:var(--text-primary); font-weight:600">{{ $c->name }}<div class="fm-muted">{{ $c->customer_code }}</div></td>
            <td><span class="badge {{ $c->customer_type === 'retailer' ? 'badge-primary' : 'badge-success' }}">{{ \App\Models\Customer::TYPES[$c->customer_type] ?? ucfirst($c->customer_type) }}</span></td>
            <td>{{ $c->district?->name ?? '—' }}<div class="fm-muted">{{ $c->district?->region?->name }}</div></td>
            <td>{{ $c->franchise?->name }}</td>
            <td>{{ $c->phone }}</td>
            <td>{!! $c->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' !!}</td>
            <td style="text-align:right">
                <div class="fm-row fm-actions" style="justify-content:flex-end">
                    <details>
                        <summary class="fm-btn fm-btn-ghost fm-btn-sm"><i class="fas fa-pen"></i></summary>
                        <form class="fm-pop fm-stack" method="POST" action="{{ route('web.admin.customers.update', $c) }}">
                            @csrf
                            <input class="fm-input" name="name" value="{{ $c->name }}" required>
                            <select class="fm-select" name="customer_type">@foreach(\App\Models\Customer::TYPES as $v => $l)<option value="{{ $v }}" @selected($c->customer_type === $v)>{{ $l }}</option>@endforeach</select>
                            <select class="fm-select" name="district_id" required>@foreach($districts as $d)<option value="{{ $d->id }}" @selected($c->district_id === $d->id)>{{ $d->name }}</option>@endforeach</select>
                            <select class="fm-select" name="franchise_id">@foreach($franchises as $f)<option value="{{ $f->id }}" @selected($c->franchise_id === $f->id)>{{ $f->name }}</option>@endforeach</select>
                            <input class="fm-input" name="phone" value="{{ $c->phone }}" placeholder="Phone">
                            <input class="fm-input" type="email" name="email" value="{{ $c->email }}" placeholder="Email">
                            <input class="fm-input" name="address" value="{{ $c->address }}" placeholder="Address">
                            <button class="fm-btn" type="submit">Save</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('web.admin.customers.toggle', $c) }}">@csrf
                        <button class="btn-action {{ $c->is_active ? 'btn-decline' : 'btn-approve' }}" title="{{ $c->is_active ? 'Deactivate' : 'Activate' }}"><i class="fas {{ $c->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i></button>
                    </form>
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="fm-muted" style="text-align:center">No customers found.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="px-4 py-3">{{ $customers->links() }}</div>
</div>
@endsection
