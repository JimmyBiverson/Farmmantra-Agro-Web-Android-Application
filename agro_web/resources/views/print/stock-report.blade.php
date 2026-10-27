@extends('print.layout')
@section('doc-title', $scope === 'company' ? 'Company (Warehouse) Stock Report' : ($selectedFranchise ? $selectedFranchise->name.' — Stock Report' : 'Franchise Stock Report'))
@section('doc-body')
<p class="meta">As at {{ now()->format('d M Y H:i') }}</p>
@include('accounts.partials.stock-table')
@endsection
