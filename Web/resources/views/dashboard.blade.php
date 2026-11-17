@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')
<div class="fm-live-status" id="fm-live-status" data-state="online" role="status"><span class="fm-live-dot"></span><span id="fm-live-label">Updated from your business records</span><button type="button" id="fm-refresh-dashboard">Refresh now</button></div>
@if(auth()->user()->isAdmin() || auth()->user()->isFinance() || auth()->user()->isFranchisePartner())
<section x-data="{summary:null}" x-init="fetch(@js(url('/workspace/data/dashboard')),{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():null).then(r=>summary=r?.data)" class="mb-4">
<template x-if="summary"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
<template x-for="[key,label] of [['payments_today','Payments today'],['payments_month','Payments this month'],['overdue_amount','Overdue over 15 days']]"><div class="card-stat"><span x-text="label"></span><strong class="block" x-text="'UGX '+Number(summary[key]).toLocaleString()"></strong></div></template>
@if(auth()->user()->isAdmin() || auth()->user()->isFinance())<a class="card-stat" href="{{ route('web.workspace') }}">Pending EFRIS invoices <strong class="block" x-text="summary.pending_invoices.length"></strong></a>@endif
</div></template>
</section>
@endif
<div id="fm-dashboard-content" data-live-url="{{ route('web.dashboard.live') }}">
    @include('partials.dashboard-content')
</div>
@endsection
