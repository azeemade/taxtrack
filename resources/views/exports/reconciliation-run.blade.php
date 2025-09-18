<table>
    <tr>
        <td colspan="12"><strong>Reconciliation Run Details</strong></td>
    </tr>
    <tr>
        <td><strong>ID</strong></td>
        <td>{{ $run->id }}</td>
        <td><strong>Account ID</strong></td>
        <td>{{ $run->account_id }}</td>
        <td><strong>Account Name</strong></td>
        <td>{{ $run->batch_id }}</td>
    </tr>
    <tr>
    <td><strong>Created At</strong></td>
    <td>{{ $run->created_at }}</td>
        <td><strong>Start Date</strong></td>
        <td>{{ $run->start_date }}</td>
        <td><strong>End Date</strong></td>
        <td>{{ $run->end_date }}</td>
    </tr>
    <tr>
        <td><strong>Discrepancies</strong></td>
        <td>{{ $run->discrepancies }}</td>
        <td><strong>Dual Reflections</strong></td>
        <td>{{ $run->dual_reflections }}</td>
        <td><strong>No Discrepancies</strong></td>
        <td>{{ $run->no_discrepancies }}</td>
    </tr>
    <tr>
        <td colspan="12"></td>
    </tr>
    <tr>
        <td colspan="12"><strong>Statistics</strong></td>
    </tr>
    @foreach ($stats as $key => $value)
        <tr>
            <td><strong>{{ Str::title(str_replace('_', ' ', $key)) }}</strong></td>
            <td>{{ $value }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="12"></td>
    </tr>
    <tr>
        <td colspan="12"><strong>Reconciliation Lines</strong></td>
    </tr>
    <tr>
        <th>Bank ID</th>
        <th>App ID</th>
        <th>Date</th>
        <th>Bank Reference</th>
        <th>App Reference</th>
        <th>Bank Debit</th>
        <th>Bank Credit</th>
        <th>App Debit</th>
        <th>App Credit</th>
        <th>Status</th>
        <th>Context</th>
    </tr>
    @foreach ($lines as $line)
        <tr>
            <td>{{ $line->bank_id }}</td>
            <td>{{ $line->app_id }}</td>
            <td>{{ $line->date }}</td>
            <td>{{ $line->bank_reference }}</td>
            <td>{{ $line->app_reference }}</td>
            <td>{{ $line->bank_debit }}</td>
            <td>{{ $line->bank_credit }}</td>
            <td>{{ $line->app_debit }}</td>
            <td>{{ $line->app_credit }}</td>
            <td>{{ $line->classification === 'no_discrepancy' ? 'matched' : ($line->classification === 'dual_reflection' ? 'mismatch' : 'unmatched') }}</td>
            <td>{{ $line->context }}</td>
        </tr>
    @endforeach
</table>