@extends('layouts.app')
@section('title', 'Terms & Conditions')
@section('page-title', 'Terms & Conditions')

@section('content')
@include('partials.fm-ui')
<div class="fm-panel" style="max-width:52rem">
    @if($needsAcceptance)
    <p class="mb-3" style="color:var(--text-primary); font-weight:600"><i class="fas fa-file-signature" style="color:var(--accent)"></i> Please read and accept the terms and conditions to continue.</p>
    @endif
    <div class="fm-terms">{{ $terms }}</div>
    <p class="fm-muted mt-2">Version {{ $version }}</p>
    @if($needsAcceptance)
    <form method="POST" action="{{ route('web.terms.accept') }}" class="fm-stack mt-4">
        @csrf
        <label class="fm-check"><input type="checkbox" name="accept" value="1" required> I have read and I accept these terms and conditions.</label>
        @error('accept')<div class="fm-err">{{ $message }}</div>@enderror
        <div class="fm-row">
            <button class="fm-btn" type="submit"><i class="fas fa-check"></i> Accept and continue</button>
        </div>
    </form>
    <form method="POST" action="{{ route('web.logout') }}" class="mt-2">@csrf<button class="fm-btn fm-btn-ghost fm-btn-sm" type="submit">Log out</button></form>
    @else
    <p class="fm-muted mt-3"><i class="fas fa-circle-check" style="color:#059669"></i> You accepted these terms on {{ auth()->user()->terms_accepted_at?->format('d M Y H:i') }}.</p>
    @endif
</div>
@endsection
