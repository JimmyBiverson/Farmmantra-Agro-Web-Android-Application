@extends('layouts.app')
@section('title', 'Bulk SMS')
@section('page-title', 'Bulk SMS')

@section('content')
@include('partials.fm-ui')
@if($driver === 'log')
<div class="fm-panel mb-4" style="border-color:#d97706"><i class="fas fa-triangle-exclamation" style="color:#d97706"></i> No SMS provider is configured (<code>SMS_DRIVER=log</code>). Messages are recorded but not delivered. Set <code>SMS_DRIVER=africastalking</code>, <code>SMS_AT_USERNAME</code> and <code>SMS_AT_API_KEY</code> in <code>.env</code> to send real SMS.</div>
@endif
<div class="fm-grid-2 mb-6" x-data="{ audience: 'franchises', msg: '' }">
    <form method="POST" action="{{ route('web.admin.sms.send') }}" class="fm-panel fm-stack" onsubmit="return confirm('Send this SMS to the selected audience?')">
        @csrf
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">New message</h3>
        <div><label class="fm-label">Send to</label>
            <select class="fm-select" name="audience" x-model="audience">
                <option value="franchises">Franchise partners</option>
                <option value="customers">Customers (retailers / farmers)</option>
                <option value="custom">Custom phone numbers</option>
            </select>
        </div>
        <div class="fm-row" x-show="audience !== 'custom'">
            <select class="fm-select" name="region_id" style="flex:1"><option value="">All regions</option>@foreach($regions as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
            <select class="fm-select" name="franchise_id" style="flex:1"><option value="">All franchises</option>@foreach($franchises as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach</select>
        </div>
        <div x-show="audience === 'customers'"><select class="fm-select" name="customer_type"><option value="">Retailers and farmers</option><option value="retailer">Retailers only</option><option value="farmer">Farmers only</option></select></div>
        <div x-show="audience === 'custom'"><label class="fm-label">Phone numbers (comma or new line separated)</label><textarea class="fm-textarea" name="numbers" placeholder="0772123456, 0701234567"></textarea></div>
        <div><label class="fm-label">Message <span class="fm-muted" x-text="msg.length + ' characters · ' + Math.max(1, Math.ceil(msg.length / 153)) + ' SMS'"></span></label><textarea class="fm-textarea" name="message" x-model="msg" required maxlength="918"></textarea></div>
        <button class="fm-btn" type="submit"><i class="fas fa-paper-plane"></i> Send</button>
    </form>
    <div class="fm-panel fm-muted">
        <p>Numbers are normalised to the Uganda format (+256…). Duplicates and invalid numbers are skipped.</p>
        <p class="mt-2">Every bulk SMS is recorded below and in the audit log.</p>
    </div>
</div>
<div class="card-full">
    <div class="card-header"><h3 class="text-sm font-semibold" style="color:var(--text-primary)">History</h3></div>
    <div class="overflow-x-auto">
    <table class="fm-table">
        <thead><tr><th>Date</th><th>Audience</th><th>Message</th><th class="num">Recipients</th><th>Status</th><th>Sent by</th></tr></thead>
        <tbody>
        @forelse($history as $h)
        <tr>
            <td>{{ $h->created_at->format('d M Y H:i') }}</td><td>{{ $h->audience }}</td><td style="max-width:28rem">{{ $h->message }}</td>
            <td class="num">{{ $h->recipient_count }}</td>
            <td><span class="badge {{ $h->status === 'sent' ? 'badge-success' : ($h->status === 'failed' ? 'badge-danger' : 'badge-warning') }}" title="{{ $h->provider_response }}">{{ ucfirst($h->status) }}</span></td>
            <td>{{ $h->sender?->name }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="fm-muted" style="text-align:center">No messages yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="px-4 py-3">{{ $history->links() }}</div>
</div>
@endsection
