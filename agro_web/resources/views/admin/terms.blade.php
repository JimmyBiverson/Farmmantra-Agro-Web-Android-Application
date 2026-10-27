@extends('layouts.app')
@section('title', 'Terms & Operations')
@section('page-title', 'Terms & Conditions and Operations Settings')

@section('content')
@include('partials.fm-ui')
<form method="POST" action="{{ route('web.admin.terms.update') }}" class="fm-panel fm-stack" style="max-width:56rem">
    @csrf
    <div><label class="fm-label">Franchise terms & conditions (current version {{ $version }})</label>
        <textarea class="fm-textarea" name="terms" style="min-height:22rem" required>{{ old('terms', $terms) }}</textarea>
    </div>
    <label class="fm-check"><input type="checkbox" name="require_reacceptance" value="1"> Publish as a new version — every franchise partner must accept it again at next login</label>
    <div class="fm-row">
        <div><label class="fm-label">Order dispatch target (days)</label><input class="fm-input" type="number" name="dispatch_target_days" min="1" max="90" value="{{ old('dispatch_target_days', $dispatchDays) }}" required style="max-width:8rem"></div>
        <label class="fm-check" style="align-self:flex-end"><input type="checkbox" name="allow_sale_discount" value="1" @checked($allowDiscount)> Allow franchises to give discounts on sales</label>
    </div>
    <p class="fm-muted">By default franchises can only sell at the listed Retail or Farmer prices (no discounts).</p>
    <button class="fm-btn" type="submit"><i class="fas fa-save"></i> Save</button>
</form>
@endsection
