@extends('print.layout')
@section('doc-title', 'Franchise Account Statement')
@section('doc-body')
<div class="grid">
    <div><span>Franchise name</span>{{ $statement['franchise']['name'] }} ({{ $statement['franchise']['code'] }})</div>
    <div><span>Person name</span>{{ $statement['franchise']['contact_person'] ?: '—' }}</div>
    <div><span>Contact</span>{{ $statement['franchise']['phone'] }} {{ $statement['franchise']['email'] }}</div>
    <div><span>Location</span>{{ $statement['franchise']['location'] ?: '—' }}</div>
    <div><span>Period</span>{{ $statement['period']['from'] ? \Illuminate\Support\Carbon::parse($statement['period']['from'])->format('d M Y') : 'Start' }} – {{ \Illuminate\Support\Carbon::parse($statement['period']['to'])->format('d M Y') }}</div>
    <div><span>Credit limit</span>UGX {{ number_format($statement['franchise']['credit_limit']) }}</div>
</div>
<table>
    <thead><tr><th>Date</th><th>Transaction details</th><th>Ref.</th><th class="num">Credit</th><th class="num">Debit</th><th class="num">Amount</th></tr></thead>
    <tbody>
        <tr><td></td><td>Opening balance</td><td></td><td></td><td></td><td class="num">{{ number_format($statement['opening_balance']) }}</td></tr>
        @foreach($statement['transactions'] as $t)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($t['date'])->format('d/m/Y') }}</td>
            <td>{{ $t['type_label'] }}<div class="muted">{{ $t['description'] }}</div></td>
            <td>{{ $t['reference_number'] }}</td>
            <td class="num">{{ $t['credit'] > 0 ? number_format($t['credit']) : '' }}</td>
            <td class="num">{{ $t['debit'] > 0 ? number_format($t['debit']) : '' }}</td>
            <td class="num">{{ number_format($t['balance']) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td></td><td>Totals</td><td></td><td class="num">{{ number_format($statement['totals']['credit']) }}</td><td class="num">{{ number_format($statement['totals']['debit']) }}</td><td></td></tr>
        <tr><td></td><td>Balance</td><td></td><td></td><td></td><td class="num">UGX {{ number_format($statement['closing_balance']) }}</td></tr>
    </tfoot>
</table>
<p class="muted">Credit = purchases. Debit = payments, credit notes and sales returns.</p>
@endsection
