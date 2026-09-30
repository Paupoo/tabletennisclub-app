{{--
    The first pages of a financial year's export: the report as the screen
    shows it — the eight tiles, the charts, the postes — from the same
    figures ({@see \App\Domains\ClubAdmin\Finance\Services\FinancialReportFigures}),
    laid out for mPDF: tables rather than a grid, hex colours, no script.
--}}
@php
    use App\Domains\Shared\Enums\MovementClosure;
    use App\Support\Charts\ChartFormat;
    use App\Support\Charts\ChartPalette;

    $euros = fn (float $amount): string => ($amount < 0 ? '−' : '') . number_format(abs($amount), 2, ',', ' ') . ' €';
    $palette = ChartPalette::print();
    $closures = MovementClosure::ordered();
    $closureCount = array_sum($justification['count']);
    $toProcessCount = $justification['count'][MovementClosure::ToProcess->value];

    // A flow against the year it is compared with, judged for the club.
    $change = function (float $current, float $previous, string $goodWhen, bool $asEuros = false) use ($previousLabel): string {
        $difference = round($current - $previous, 2);
        $text = $asEuros
            ? ($difference > 0 ? '+' : ($difference < 0 ? '−' : '')) . ChartFormat::euros(abs($difference))
            : ChartFormat::change($current, $previous);

        if ($text === null) {
            return '<span class="muted">' . e(__('Nothing to compare with in :year', ['year' => $previousLabel])) . '</span>';
        }

        $direction = $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat');
        $class = $direction === 'flat' ? 'muted' : ($direction === $goodWhen ? 'good' : 'bad');

        return '<span class="' . $class . '">' . e($text . ' ' . __('vs :year', ['year' => $previousLabel])) . '</span>';
    };
@endphp

@include('financial-export.styles')

<h1>{{ $clubName }} — {{ __('Financial report') }} {{ $yearLabel }}</h1>
<div class="meta">
    {{ __('Financial year :year, compared with :previous', ['year' => $yearLabel, 'previous' => $previousLabel]) }}
    @if ($filters !== [])
        · {{ implode(' · ', $filters) }}
    @endif
    <br>{{ __('Exported on :date by :name', ['date' => $exportedAt->format('d/m/Y H:i'), 'name' => $exportedBy]) }}
</div>

<table class="tiles">
    <tr>
        <td>
            <div class="tile-label">{{ __('Income') }}</div>
            <div class="tile-value">{{ $euros($flows['income'][0]) }}</div>
            <div class="tile-line">{!! $change($flows['income'][0], $flows['income'][1], 'up') !!}</div>
        </td>
        <td>
            <div class="tile-label">{{ __('Expenses') }}</div>
            <div class="tile-value">{{ $euros($flows['expenses'][0]) }}</div>
            <div class="tile-line">{!! $change($flows['expenses'][0], $flows['expenses'][1], 'down') !!}</div>
        </td>
        <td>
            <div class="tile-label">{{ __('Result') }}</div>
            <div class="tile-value">{{ $euros($flows['result'][0]) }}</div>
            <div class="tile-line">{{ __('Income minus expenses') }}</div>
            <div class="tile-line">{!! $change($flows['result'][0], $flows['result'][1], 'up', true) !!}</div>
        </td>
        <td>
            <div class="tile-label">{{ __('Money accounted for') }}</div>
            <div class="tile-value">{{ $justification['closed_amount_share'] === null ? '—' : str_replace('.', ',', (string) $justification['closed_amount_share']) . ' %' }}</div>
            <div class="tile-line">
                {{ $closureCount === 0
                    ? __('No movement this year')
                    : trans_choice(':closed movement of :total has its supporting evidence|:closed movements of :total have their supporting evidence', $closureCount - $toProcessCount, ['closed' => $closureCount - $toProcessCount, 'total' => $closureCount]) }}
            </div>
            @if ($toProcessCount > 0)
                <div class="tile-line warn">{{ trans_choice(':count is still to process|:count are still to process', $toProcessCount) }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td>
            <div class="tile-label">{{ __('Members who still owe money') }}</div>
            <div class="tile-value">{{ $members['active'] === 0 ? '—' : round(100 * $members['count'] / $members['active']) . ' %' }}</div>
            <div class="tile-line">{{ __(':count of :active active members, today', ['count' => $members['count'], 'active' => $members['active']]) }}</div>
        </td>
        <td>
            <div class="tile-label">{{ __('Money still expected') }}</div>
            <div class="tile-value">{{ $euros($receivables['amount']) }}</div>
            <div class="tile-line">{{ trans_choice(':count amount the club is still waiting for|:count amounts the club is still waiting for', $receivables['count']) }}</div>
        </td>
        <td>
            <div class="tile-label">{{ __('Money the club still owes') }}</div>
            <div class="tile-value">{{ $euros($debts['amount']) }}</div>
            <div class="tile-line">{{ trans_choice(':count amount the club still has to pay|:count amounts the club still has to pay', $debts['count']) }}</div>
        </td>
        <td>
            <div class="tile-label">{{ __('Treasury') }}</div>
            <div class="tile-value">{{ $euros($treasury['total']) }}</div>
            <div class="tile-line">{{ __('Held on :date', ['date' => $treasuryDay->format('d/m/Y')]) }}</div>
            <div class="tile-line">{{ __(':amount since :date', ['amount' => ($treasury['change'] > 0 ? '+' : '') . $euros($treasury['change']), 'date' => $yearStartLabel]) }}</div>
            @foreach ($treasury['stale'] as $stale)
                <div class="tile-line warn">
                    {{ $stale['as_of'] === null
                        ? __(':name: no balance known', ['name' => $stale['name']])
                        : __(':name: last known balance on :date', ['name' => $stale['name'], 'date' => $stale['as_of']->format('d/m/Y')]) }}
                </div>
            @endforeach
        </td>
    </tr>
</table>

<h2>{{ __('Treasury through the year') }}</h2>
<div class="chart">
    <x-charts.stacked-columns id="pdf-treasury" :series="$holdings['series']" :columns="$holdings['columns']" :palette="$palette" :interactive="false"
        :title="__('Money held at each month end, :year', ['year' => $yearLabel])" />
</div>

<h2>{{ __('Income and expenses per month') }}</h2>
<div class="chart">
    <x-charts.monthly-flows id="pdf-monthly" :months="$months" :palette="$palette" :interactive="false"
        :title="__('Income and expenses per month, :year', ['year' => $yearLabel])"
        :labels="['income' => __('Income'), 'expenses' => __('Expenses'), 'cumulative' => __('Cumulative result')]" />
</div>

<pagebreak />

@foreach ([
    ['title' => __('Expenses per poste'), 'chart' => __('Expenses per poste, :year compared with :previous', ['year' => $yearLabel, 'previous' => $previousLabel]), 'rows' => $expenseRows, 'total' => $flows['expenses'], 'id' => 'pdf-expenses'],
    ['title' => __('Income per poste'), 'chart' => __('Income per poste, :year compared with :previous', ['year' => $yearLabel, 'previous' => $previousLabel]), 'rows' => $incomeRows, 'total' => $flows['income'], 'id' => 'pdf-income'],
] as $table)
    <h2>{{ $table['title'] }}</h2>
    @if ($table['rows'] !== [])
        <div class="chart">
            <x-charts.paired-bars :id="$table['id']" :rows="$table['rows']" :palette="$palette" :interactive="false"
                :current-label="$yearLabel" :previous-label="$previousLabel"
                :title="$table['chart']" />
        </div>
    @endif
    <table>
        <thead>
            <tr>
                <th>{{ __('Poste') }}</th>
                <th class="num">{{ $yearLabel }}</th>
                <th class="num">{{ $previousLabel }}</th>
                <th class="num">{{ __('Evolution') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($table['rows'] as $row)
                <tr>
                    <td @class(['warn' => $row['uncategorised']])>{{ $row['label'] }}</td>
                    <td class="num">{{ $euros($row['current']) }}</td>
                    <td class="num">{{ $euros($row['previous']) }}</td>
                    <td class="num">{{ ChartFormat::change($row['current'], $row['previous']) ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">{{ __('Nothing this year nor the year before.') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th>{{ __('Total') }}</th>
                <th class="num">{{ $euros($table['total'][0]) }}</th>
                <th class="num">{{ $euros($table['total'][1]) }}</th>
                <th class="num">{{ ChartFormat::change($table['total'][0], $table['total'][1]) ?? '—' }}</th>
            </tr>
        </tfoot>
    </table>
@endforeach

<pagebreak />

<table>
    <tr>
        <td style="width:50%;border:none;padding-right:4mm">
            <h2>{{ __('Trainings: what they bring and what they cost') }}</h2>
            <table>
                <thead><tr><th></th><th class="num">{{ $yearLabel }}</th><th class="num">{{ $previousLabel }}</th></tr></thead>
                <tbody>
                    <tr><td>{{ __('Trainings') }} ({{ __('income') }})</td><td class="num">{{ $euros($trainings['income'][0]) }}</td><td class="num">{{ $euros($trainings['income'][1]) }}</td></tr>
                    <tr><td>{{ __('Training & education') }} ({{ __('expense') }})</td><td class="num">{{ $euros($trainings['expense'][0]) }}</td><td class="num">{{ $euros($trainings['expense'][1]) }}</td></tr>
                </tbody>
                <tfoot>
                    <tr><th>{{ __('Balance') }}</th><th class="num">{{ $euros($trainings['income'][0] - $trainings['expense'][0]) }}</th><th class="num">{{ $euros($trainings['income'][1] - $trainings['expense'][1]) }}</th></tr>
                </tfoot>
            </table>
        </td>
        <td style="width:50%;border:none;padding-left:4mm">
            <h2>{{ __('Membership fees by licence') }}</h2>
            <table>
                <thead><tr><th></th><th class="num">{{ $yearLabel }}</th><th class="num">{{ $previousLabel }}</th></tr></thead>
                <tbody>
                    <tr><td>{{ __('Recreational') }}</td><td class="num">{{ $euros($fees[0]['recreational']) }}</td><td class="num">{{ $euros($fees[1]['recreational']) }}</td></tr>
                    <tr><td>{{ __('Competitive') }}</td><td class="num">{{ $euros($fees[0]['competitive']) }}</td><td class="num">{{ $euros($fees[1]['competitive']) }}</td></tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>

<h2>{{ __('How the movements were closed') }}</h2>
<div class="chart">
    <x-charts.share-bars id="pdf-closure" :palette="$palette" :interactive="false"
        :title="__('How the movements of :year were closed', ['year' => $yearLabel])"
        :segments="array_map(fn ($closure) => ['key' => $closure->value, 'label' => $closure->label()], $closures)"
        :rows="[
            ['label' => __('Number'), 'values' => $justification['count'], 'format' => 'count'],
            ['label' => __('Amount'), 'values' => $justification['amount'], 'format' => 'euros'],
        ]" />
</div>
<table>
    <thead><tr><th>{{ __('State') }}</th><th class="num">{{ __('Movements') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
    <tbody>
        @foreach ($closures as $closure)
            <tr>
                <td>{{ $closure->label() }}</td>
                <td class="num">{{ $justification['count'][$closure->value] }}</td>
                <td class="num">{{ $euros($justification['amount'][$closure->value]) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>{{ __('Internal movements') }}</h2>
<p class="caption">{{ __('Money moved between the club\'s own accounts, or between the till and the bank: neither income nor expense.') }}</p>
@if ($internalMovements === [])
    <p class="muted">{{ __('None this year.') }}</p>
@else
    <table>
        <tbody>
            @foreach ($internalMovements as $movement)
                <tr>
                    <td>{{ $movement['date']->format('d/m/Y') }}</td>
                    <td>{{ $movement['source'] }}</td>
                    <td>{{ $movement['description'] }}</td>
                    <td class="num">{{ $euros($movement['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
