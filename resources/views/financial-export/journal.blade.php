{{--
    The journal of the year: every bank line and cash movement, what feeds
    which poste, how it was closed and what justifies it — the list the
    auditors tick off against the statements.
--}}
@php
    use App\Domains\ClubAdmin\Finance\Services\FinancialReport;

    $euros = fn (float $amount): string => ($amount < 0 ? '−' : '') . number_format(abs($amount), 2, ',', ' ');
@endphp

@include('financial-export.styles')

<h2>{{ __('Journal of :year', ['year' => $yearLabel]) }}</h2>
<p class="caption">{{ trans_choice(':count movement|:count movements', count($journal)) }}@if ($filters !== []) · {{ implode(' · ', $filters) }}@endif</p>

<table style="font-size:7.5pt">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Account or till') }}</th>
            <th>{{ __('Statement') }}</th>
            <th>{{ __('Counterparty') }}</th>
            <th>{{ __('Poste') }}</th>
            <th>{{ __('State') }}</th>
            <th>{{ __('Pieces') }}</th>
            <th class="num">{{ __('Amount') }} (€)</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($journal as $row)
            <tr>
                <td style="white-space:nowrap">{{ $row['date']->format('d/m/Y') }}</td>
                <td>{{ $row['source'] }}</td>
                <td>{{ $row['statement'] ?? '' }}</td>
                <td>
                    {{ $row['counterparty'] ?? $row['description'] }}
                    @if ($row['counterparty'])
                        <br><span class="muted">{{ \Illuminate\Support\Str::limit($row['description'], 60) }}</span>
                    @endif
                </td>
                <td>{{ implode(', ', array_map(FinancialReport::posteLabel(...), $row['postes'])) }}</td>
                <td @class(['warn' => $row['closure'] === \App\Domains\Shared\Enums\MovementClosure::ToProcess])>{{ $row['closure']->label() }}</td>
                <td>{{ implode(', ', [...$row['documents'], ...array_map(fn (int $id): string => __('Expense report #:id', ['id' => $id]), $row['expense_reports'])]) }}</td>
                <td class="num">{{ $euros($row['amount']) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">{{ __('No movement this year') }}</td></tr>
        @endforelse
    </tbody>
</table>
