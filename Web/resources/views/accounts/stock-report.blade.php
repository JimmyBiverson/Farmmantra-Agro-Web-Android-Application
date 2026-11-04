@extends('layouts.app')
@section('title', 'Stock Reports')
@section('page-title', 'Stock Reports')
@section('content')
@include('partials.fm-ui')
@php
    $title = $scope === 'company' ? 'Company (warehouse) stock report' : ($selectedFranchise ? $selectedFranchise->name.' — stock report' : 'Franchise stock report (all franchises)');
@endphp
<form method="GET" class="fm-panel fm-row mb-4">
    @unless(auth()->user()->isFranchisePartner())
    <div><label class="fm-label">Report</label>
        <select class="fm-select" name="scope" onchange="this.form.submit()">
            <option value="company" @selected($scope === 'company')>Company / warehouse stock</option>
            <option value="franchise" @selected($scope === 'franchise')>Franchise stock</option>
        </select>
    </div>
    @if($scope === 'franchise')
    <div><label class="fm-label">Franchise</label>
        <select class="fm-select" name="franchise_id" onchange="this.form.submit()">
            <option value="">All franchises</option>
            @foreach($franchises as $f)<option value="{{ $f->id }}" @selected((string) $franchiseId === (string) $f->id)>{{ $f->name }}</option>@endforeach
        </select>
    </div>
    @endif
    @endunless
    <div style="margin-left:auto; align-self:flex-end">
        <a class="fm-btn fm-btn-ghost" target="_blank" href="{{ route('web.print', ['type' => 'stock-report', 'id' => (int) ($franchiseId ?? 0), 'scope' => $scope]) }}"><i class="fas fa-print"></i> Print</a>
    </div>
</form>
<div class="fm-panel overflow-x-auto">
    <h3 class="text-sm font-semibold mb-3" style="color:var(--text-primary)">{{ $title }}</h3>
    @include('accounts.partials.stock-table')
</div>
@endsection
