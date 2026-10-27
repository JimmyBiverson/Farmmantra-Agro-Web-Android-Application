@extends('layouts.app')
@section('title', 'Account Statement')
@section('page-title', 'Franchise Account Statement')

@section('content')
@include('partials.fm-ui')
@php $isFranchise = auth()->user()->isFranchisePartner(); @endphp

<form method="GET" class="fm-panel fm-row mb-4">
    @unless($isFranchise)
    <div style="min-width:14rem; flex:1"><label class="fm-label">Franchise</label>
        <select class="fm-select" name="franchise_id" required>
            <option value="">Select franchise…</option>
            @foreach($franchises as $f)
            <option value="{{ $f->id }}" @selected($selected?->id === $f->id)>{{ $f->name }} — balance UGX {{ number_format($f->account_balance) }}</option>
            @endforeach
        </select>
    </div>
    @endunless
    <div><label class="fm-label">From</label><input class="fm-input" type="date" name="from" value="{{ request('from', now()->startOfMonth()->toDateString()) }}"></div>
    <div><label class="fm-label">To</label><input class="fm-input" type="date" name="to" value="{{ request('to', now()->toDateString()) }}"></div>
    <div style="align-self:flex-end" class="fm-row">
        <button class="fm-btn" type="submit"><i class="fas fa-magnifying-glass"></i> Show</button>
        @if($statement)
        <a class="fm-btn fm-btn-ghost" target="_blank" href="{{ route('web.print', ['type' => 'statement', 'id' => $statement['franchise']['id'], 'from' => request('from', now()->startOfMonth()->toDateString()), 'to' => request('to', now()->toDateString())]) }}"><i class="fas fa-print"></i> Print</a>
        @endif
    </div>
</form>

@if($statement)
@include('accounts.partials.statement-table', ['statement' => $statement])
@else
<div class="fm-panel fm-muted">Choose a franchise to see its account statement.</div>
@endif
@endsection
