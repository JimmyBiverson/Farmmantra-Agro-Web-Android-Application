<table class="fm-table">
    <thead><tr>
        @if($scope === 'franchise' && ! $franchiseId)<th>Franchise</th>@endif
        <th>Product</th><th>SKU</th><th class="num">Quantity</th><th class="num">Reorder level</th><th class="num">Unit cost</th><th class="num">Value (UGX)</th><th>Status</th>
    </tr></thead>
    <tbody>
    @forelse($items as $i)
        @php $value = (float) $i->quantity * (float) ($i->product?->standard_price ?? 0); $low = (float) $i->quantity <= (float) $i->reorder_level; @endphp
        <tr>
            @if($scope === 'franchise' && ! $franchiseId)<td>{{ $i->franchise?->name }}</td>@endif
            <td>{{ $i->product?->name }}</td>
            <td>{{ $i->product?->sku }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float) $i->quantity, 2), '0'), '.') }} {{ $i->product?->unit_of_measure }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float) $i->reorder_level, 2), '0'), '.') }}</td>
            <td class="num">{{ number_format((float) ($i->product?->standard_price ?? 0)) }}</td>
            <td class="num">{{ number_format($value) }}</td>
            <td>{!! $low ? '<span class="badge badge-danger">Low</span>' : '<span class="badge badge-success">OK</span>' !!}</td>
        </tr>
    @empty
        <tr><td colspan="8" style="text-align:center">No stock records.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td colspan="{{ $scope === 'franchise' && ! $franchiseId ? 6 : 5 }}" style="font-weight:700">Total stock value</td><td class="num" style="font-weight:700">UGX {{ number_format($totalValue) }}</td><td></td></tr></tfoot>
</table>
