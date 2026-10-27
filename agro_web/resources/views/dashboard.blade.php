@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php $role = auth()->user()->role?->name; @endphp

@if($role === 'System Administrator')
    @include('partials.admin-dashboard')
@elseif(in_array($role, ['Farmmantra Staff', 'Warehouse']))
    @include('partials.staff-dashboard')
@elseif($role === 'Finance Department')
    @include('partials.finance-dashboard')
@elseif($role === 'Franchise Partner')
    @include('partials.franchise-dashboard')
@endif
@endsection
