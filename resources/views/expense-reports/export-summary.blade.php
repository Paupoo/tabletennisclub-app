{{-- The first pages of an expense reports export: what an auditor reads first. --}}
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #111827; }
    h1 { font-size: 14pt; color: #154a8a; margin: 0 0 2mm; }
    .meta { font-size: 8.5pt; color: #4b5563; margin-bottom: 6mm; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; border-bottom: 1.5px solid #154a8a; padding: 1.5mm 1mm; font-size: 8pt; color: #4b5563; }
    td { border-bottom: 0.5px solid #d1d5db; padding: 1.5mm 1mm; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .totals td { font-weight: bold; border-bottom: none; }
    h2 { font-size: 11pt; margin: 8mm 0 2mm; }
</style>

<h1>{{ $clubName }} — {{ __('Expense reports') }}</h1>
<div class="meta">
    {{ trans_choice(':count report|:count reports', $reports->count()) }}
    · {{ __('Exported on :date by :name', ['date' => $exportedAt->format('d/m/Y H:i'), 'name' => $exportedBy]) }}
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('Member') }}</th>
            <th>{{ __('Nature') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Spent on') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Paid on') }}</th>
            <th class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['id'] }}</td>
                <td>{{ $row['member'] }}</td>
                <td>{{ $row['category'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td>{{ $row['spent_on'] }}</td>
                <td>{{ $row['status'] }}</td>
                <td>{{ $row['paid_on'] }}</td>
                <td class="num">{{ $row['amount'] }} €</td>
            </tr>
        @endforeach
        <tr class="totals">
            <td colspan="7">{{ __('Total') }}</td>
            <td class="num">{{ $total }} €</td>
        </tr>
    </tbody>
</table>

<h2>{{ __('By nature') }}</h2>
<table>
    @foreach ($byCategory as $category => $amount)
        <tr>
            <td>{{ $category }}</td>
            <td class="num">{{ $amount }} €</td>
        </tr>
    @endforeach
</table>
