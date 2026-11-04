@php
    $role = auth()->user()->role?->name;
    $charts = [
        'salesTrendChart' => ['type' => 'line', 'labels' => collect($dashboard['sales_trend'] ?? [])->pluck('date'), 'values' => collect($dashboard['sales_trend'] ?? [])->pluck('total_sales')],
        'staffOrderStatusChart' => ['type' => 'doughnut', 'labels' => collect($dashboard['orders_by_status'] ?? [])->pluck('status'), 'values' => collect($dashboard['orders_by_status'] ?? [])->pluck('count')],
        'categoryChart' => ['type' => 'doughnut', 'labels' => collect($dashboard['sales_by_category'] ?? [])->pluck('category_name'), 'values' => collect($dashboard['sales_by_category'] ?? [])->pluck('total_sales')],
    ];
@endphp
@if($role === 'System Administrator')
    @include('partials.admin-dashboard')
@elseif(in_array($role, ['Farmmantra Staff', 'Warehouse']))
    @include('partials.staff-dashboard')
@elseif($role === 'Finance Department')
    @include('partials.finance-dashboard')
@elseif($role === 'Franchise Partner')
    @include('partials.franchise-dashboard')
@endif
<script type="application/json" data-dashboard-charts>@json($charts)</script>
