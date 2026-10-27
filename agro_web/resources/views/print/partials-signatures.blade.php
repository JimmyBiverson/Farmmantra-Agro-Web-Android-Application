@if(isset($signatures) && $signatures->count())
<h3 style="font-size:.85rem; margin:1rem 0 .3rem">Digital signatures</h3>
@foreach($signatures as $sig)
<div class="sig"><code>{{ $sig->signature_number }}</code> — {{ $sig->action_label }} by <strong>{{ $sig->user_name }}</strong> ({{ $sig->user_role }}) on {{ $sig->signed_at->format('d M Y H:i') }}</div>
@endforeach
@endif
