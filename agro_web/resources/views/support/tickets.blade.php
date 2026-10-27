@extends('layouts.app')
@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')

@section('content')
@include('partials.fm-ui')
<div class="fm-grid-2">
    <div class="fm-panel">
        <h3 class="text-sm font-semibold mb-3" style="color:var(--text-primary)">Raise a ticket</h3>
        <form method="POST" action="{{ route('web.support.tickets.store') }}" class="fm-stack">
            @csrf
            <div><label class="fm-label">Subject</label><input class="fm-input" name="subject" required maxlength="255" value="{{ old('subject') }}"></div>
            <div class="fm-row">
                <div style="flex:1"><label class="fm-label">Category</label>
                    <select class="fm-select" name="category">
                        @foreach(['Orders', 'Deliveries', 'Payments', 'Stock', 'Pricing', 'System / App', 'General'] as $c)<option>{{ $c }}</option>@endforeach
                    </select>
                </div>
                <div style="flex:1"><label class="fm-label">Priority</label>
                    <select class="fm-select" name="priority">
                        @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $v => $l)<option value="{{ $v }}" @selected($v === 'medium')>{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div><label class="fm-label">Describe the issue</label><textarea class="fm-textarea" name="message" required maxlength="5000">{{ old('message') }}</textarea></div>
            <button class="fm-btn" type="submit"><i class="fas fa-paper-plane"></i> Submit ticket</button>
        </form>
    </div>
    <div class="fm-panel">
        <div class="fm-row mb-3">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary)">{{ $isAdmin ? 'All tickets' : 'My tickets' }}</h3>
            <form method="GET" style="margin-left:auto">
                <select class="fm-select" name="status" onchange="this.form.submit()" style="width:auto">
                    <option value="">All</option>
                    @foreach(['open', 'in_progress', 'resolved', 'closed'] as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>@endforeach
                </select>
            </form>
        </div>
        @forelse($tickets as $t)
        <div style="border-bottom:1px solid var(--border-color); padding:.75rem 0">
            <div class="fm-row">
                <strong style="color:var(--text-primary)">{{ $t->subject }}</strong>
                <span class="badge {{ $t->priority === 'urgent' ? 'badge-danger' : ($t->priority === 'high' ? 'badge-warning' : 'badge-info') }}">{{ ucfirst($t->priority) }}</span>
                <span class="badge {{ in_array($t->status, ['resolved', 'closed']) ? 'badge-success' : 'badge-primary' }}">{{ ucfirst(str_replace('_', ' ', $t->status)) }}</span>
            </div>
            <div class="fm-muted">{{ $t->ticket_number }} · {{ $t->category }} · {{ $t->user?->name }} ({{ $t->user?->role?->name }}) · {{ $t->created_at->format('d M Y H:i') }}</div>
            <p style="margin:.4rem 0; white-space:pre-wrap; font-size:.85rem; color:var(--text-secondary)">{{ $t->message }}</p>
            @if($t->admin_response)
            <div style="border-left:3px solid var(--accent); padding-left:.6rem; font-size:.82rem; color:var(--text-primary)"><strong>Response:</strong> {{ $t->admin_response }}</div>
            @endif
            @if($isAdmin)
            <form method="POST" action="{{ route('web.support.tickets.respond', $t) }}" class="fm-row" style="margin-top:.5rem">
                @csrf
                <input class="fm-input" name="admin_response" placeholder="Reply…" style="flex:1; min-width:12rem">
                <select class="fm-select" name="status" style="width:auto">
                    @foreach(['open', 'in_progress', 'resolved', 'closed'] as $st)<option value="{{ $st }}" @selected($t->status === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>@endforeach
                </select>
                <button class="fm-btn fm-btn-sm" type="submit">Update</button>
            </form>
            @endif
        </div>
        @empty
        <p class="fm-muted">No tickets yet.</p>
        @endforelse
        <div class="mt-3">{{ $tickets->links() }}</div>
    </div>
</div>
@endsection
