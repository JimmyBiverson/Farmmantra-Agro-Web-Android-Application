@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')
<div class="fm-live-status" id="fm-live-status" data-state="online" role="status"><span class="fm-live-dot"></span><span id="fm-live-label">Updated from your business records</span><button type="button" id="fm-refresh-dashboard">Refresh now</button></div>
<div id="fm-dashboard-content" data-live-url="{{ route('web.dashboard.live') }}">
    @include('partials.dashboard-content')
</div>
@endsection
