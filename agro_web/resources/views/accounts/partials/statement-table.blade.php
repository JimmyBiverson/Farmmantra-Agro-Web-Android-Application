{{-- Client layout: Transaction details | Credit | Debit | Amount (running balance) --}}
<div class="fm-panel">
    <div class="fm-grid mb-3">
        <div><div class="fm-muted">Franchise</div><div style="color:var(--text-primary); font-weight:600">{{ $statement['franchise']['name'] }} ({{ $statement['franchise']['code'] }})</div></div>
        <div><div class="fm-muted">Contact person</div><div style="color:var(--text-primary)">{{ $statement['franchise']['contact_person'] ?: '—' }}</div></div>
        <div><div class="fm-muted">Contact</div><div style="color:var(--text-primary)">{{ $statement['franchise']['phone'] ?: '—' }} {{ $statement['franchise']['email'] ? '· '.$statement['franchise']['email'] : '' }}</div></div>
        <div><div class="fm-muted">Location</div><div style="color:var(--text-primary)">{{ $statement['franchise']['location'] ?: '—' }}</div></div>
        <div><div class="fm-muted">Period</div><div style="color:var(--text-primary)">{{ $statement['period']['from'] ? \Illuminate\Support\Carbon::parse($statement['period']['from'])->format('d M Y') : 'Start' }} – {{ \Illuminate\Support\Carbon::parse($statement['period']['to'])->format('d M Y') }}</div></div>
    </div>
    <div class="overflow-x-auto">
    <table class="fm-table">
        <thead>
            <tr><th>Date</th><th>Transaction details</th><th>Reference</th><th class="num">Credit</th><th class="num">Debit</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            <tr><td></td><td style="font-weight:600">Opening balance</td><td></td><td></td><td></td><td class="num" style="font-weight:600">{{ number_format($statement['opening_balance']) }}</td></tr>
            @forelse($statement['transactions'] as $t)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($t['date'])->format('d M Y') }}</td>
                <td><span style="color:var(--text-primary)">{{ $t['type_label'] }}</span><div class="fm-muted">{{ $t['description'] }}</div></td>
                <td class="fm-sig">{{ $t['reference_number'] }}</td>
                <td class="num">{{ $t['credit'] > 0 ? number_format($t['credit']) : '' }}</td>
                <td class="num">{{ $t['debit'] > 0 ? number_format($t['debit']) : '' }}</td>
                <td class="num">{{ number_format($t['balance']) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="fm-muted" style="text-align:center">No transactions in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td></td><td style="font-weight:600">Totals</td><td></td><td class="num" style="font-weight:600">{{ number_format($statement['totals']['credit']) }}</td><td class="num" style="font-weight:600">{{ number_format($statement['totals']['debit']) }}</td><td></td></tr>
            <tr><td></td><td style="font-weight:700; color:var(--text-primary)">Balance</td><td></td><td></td><td></td><td class="num" style="font-weight:700; color:var(--text-primary)">UGX {{ number_format($statement['closing_balance']) }}</td></tr>
        </tfoot>
    </table>
    </div>
    <p class="fm-muted mt-2">Credit = purchases (amount owed increases). Debit = payments, credit notes and sales returns (amount owed decreases). A negative balance means the franchise has paid in advance.</p>
</div>
