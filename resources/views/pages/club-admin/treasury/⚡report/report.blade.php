@php
    use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
    use App\Domains\Shared\Enums\MovementClosure;
    use App\Support\Charts\ChartFormat;
    use App\Support\Charts\ChartPalette;

    // A true minus sign: a hyphen reads as a dash in a column of figures.
    $euros = fn (float $amount): string => ($amount < 0 ? '−' : '') . number_format(abs($amount), 2, ',', ' ') . ' €';
    $palette = ChartPalette::css();
    $closures = MovementClosure::ordered();
    $closureCount = array_sum($justification['count']);
@endphp

<div data-financial-report>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header :title="__('Financial report')" :subtitle="__('Financial year :year, compared with :previous', ['year' => $yearLabel, 'previous' => $previousLabel])"
        separator progress-indicator>
        <x-slot:actions>
            <x-button data-print-hide :label="__('Print')" icon="o-printer" class="btn-sm" x-on:click="window.print()" />
        </x-slot:actions>
    </x-header>

    <div data-print-hide>
        <x-admin.shared.season-nav model="fiscalYear" :options="$yearOptions" :label="__('Financial year')"
            :placeholder="__('Select a financial year')" />
    </div>

    <x-tabs wire:model.live="tab">
        {{-- ── Overview ───────────────────────────────────────────────── --}}
        <x-tab name="overview" :label="__('Overview')" icon="o-chart-bar">
            {{-- Flows of the year, compared with the year before --}}
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-report-tiles>
                <x-admin.shared.stat-card :label="__('Income')" :value="$euros($flows['income'][0])" icon="o-arrow-down-tray" color="primary">
                    <x-slot:extra>
                        <x-admin.finance.change :current="$flows['income'][0]" :previous="$flows['income'][1]" :previous-label="$previousLabel" good-when="up" />
                    </x-slot:extra>
                </x-admin.shared.stat-card>

                <x-admin.shared.stat-card :label="__('Expenses')" :value="$euros($flows['expenses'][0])" icon="o-arrow-up-tray" color="primary">
                    <x-slot:extra>
                        <x-admin.finance.change :current="$flows['expenses'][0]" :previous="$flows['expenses'][1]" :previous-label="$previousLabel" good-when="down" />
                    </x-slot:extra>
                </x-admin.shared.stat-card>

                <x-admin.shared.stat-card :label="__('Result')" :value="$euros($flows['result'][0])" :hint="__('Income minus expenses')" icon="o-scale"
                    :color="$flows['result'][0] >= 0 ? 'success' : 'error'">
                    <x-slot:extra>
                        <x-admin.finance.change :current="$flows['result'][0]" :previous="$flows['result'][1]" :previous-label="$previousLabel" good-when="up" as="euros" />
                    </x-slot:extra>
                </x-admin.shared.stat-card>

                {{-- One percentage — the money — and the rest in words: two
                     percentages side by side read as jargon. --}}
                @php
                    $toProcessCount = $justification['count'][MovementClosure::ToProcess->value];
                    $closedCount = $closureCount - $toProcessCount;
                @endphp
                <x-admin.shared.stat-card :label="__('Money accounted for')" data-tile="accounted-for"
                    :value="$justification['closed_amount_share'] === null ? '—' : str_replace('.', ',', (string) $justification['closed_amount_share']) . ' %'"
                    :hint="$closureCount === 0
                        ? __('No movement this year')
                        : trans_choice(':closed movement of :total has its supporting evidence|:closed movements of :total have their supporting evidence', $closedCount, ['closed' => $closedCount, 'total' => $closureCount])"
                    :help="__('A movement is accounted for when it is reconciled with a website payment, covered by a supporting document, or is an internal transfer.')"
                    icon="o-check-badge"
                    :color="($justification['closed_amount_share'] ?? 100) >= 95 ? 'success' : 'warning'">
                    @if ($toProcessCount > 0)
                        <x-slot:extra>
                            @php $toProcessText = trans_choice(':count is still to process|:count are still to process', $toProcessCount); @endphp
                            <div class="mt-1 text-xs font-semibold text-warning-content" data-to-process>
                                @can('transactions.view')
                                    <a class="link link-hover" wire:navigate
                                        href="{{ route('admin.treasury.transactions', ['state' => 'to_process', 'from' => $yearStart, 'to' => $yearEnd]) }}">{{ $toProcessText }}</a>
                                @else
                                    {{ $toProcessText }}
                                @endcan
                            </div>
                        </x-slot:extra>
                    @endif
                </x-admin.shared.stat-card>

                {{-- States: where the club stands today, never compared --}}
                <x-admin.shared.stat-card :label="__('Members who still owe money')"
                    :value="$members['active'] === 0 ? '—' : round(100 * $members['count'] / $members['active']) . ' %'"
                    :hint="__(':count of :active active members, today', ['count' => $members['count'], 'active' => $members['active']])"
                    icon="o-user-group" :color="$members['count'] > 0 ? 'warning' : 'neutral'" />

                <x-admin.shared.stat-card :label="__('Money still expected')" :value="$euros($receivables['amount'])"
                    :hint="trans_choice(':count amount the club is still waiting for|:count amounts the club is still waiting for', $receivables['count'])"
                    icon="o-arrow-down-circle" :color="$receivables['count'] > 0 ? 'warning' : 'neutral'" />

                <x-admin.shared.stat-card :label="__('Money the club still owes')" :value="$euros($debts['amount'])"
                    :hint="trans_choice(':count amount the club still has to pay|:count amounts the club still has to pay', $debts['count'])"
                    icon="o-arrow-up-circle" :color="$debts['count'] > 0 ? 'warning' : 'neutral'" />

{{-- The total and how it moved since the year began; an account shows up
                     only when its balance is too old to trust. The detail is
                     the chart below. --}}
                <x-admin.shared.stat-card :label="__('Treasury')" :value="$euros($treasury['total'])" data-tile="treasury"
                    :hint="__('Held on :date', ['date' => $treasuryDay->format('d/m/Y')])" icon="o-building-library" color="primary">
                    <x-slot:extra>
                        <div class="mt-1 text-xs font-semibold text-muted tabular-nums" data-treasury-change>
                            {{ __(':amount since :date', ['amount' => ($treasury['change'] > 0 ? '+' : '') . $euros($treasury['change']), 'date' => $yearStartLabel]) }}
                        </div>
                        @foreach ($treasury['stale'] as $stale)
                            <div class="mt-1 flex items-start gap-1 text-xs text-warning-content" data-treasury-stale>
                                <x-icon name="o-exclamation-triangle" class="h-4 w-4 shrink-0" />
                                <span>{{ $stale['as_of'] === null
                                    ? __(':name: no balance known', ['name' => $stale['name']])
                                    : __(':name: last known balance on :date', ['name' => $stale['name'], 'date' => $stale['as_of']->format('d/m/Y')]) }}</span>
                            </div>
                        @endforeach
                    </x-slot:extra>
                </x-admin.shared.stat-card>
            </div>

            {{-- Charts --}}
            <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <x-card :title="__('Treasury through the year')" class="shadow-sm xl:col-span-2" separator data-print-keep>
                    <x-charts.stacked-columns id="chart-treasury" :series="$holdings['series']" :columns="$holdings['columns']" :palette="$palette"
                        :title="__('Money held at each month end, :year', ['year' => $yearLabel])"
                        :description="__('One column per month end, stacked by bank account with the tills on top; a year in progress stops today.')" />

                    <details class="mt-3 text-sm" data-print-hide>
                        <summary class="cursor-pointer text-muted">{{ __('Show the figures') }}</summary>
                        <div class="mt-2 overflow-x-auto">
                            <table class="table table-sm" data-treasury-figures>
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        @foreach ($holdings['series'] as $serie)
                                            <th class="text-right">{{ $serie['label'] }}</th>
                                        @endforeach
                                        <th class="text-right">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($holdings['columns'] as $column)
                                        <tr>
                                            <td class="tabular-nums">{{ $column['day']->format('d/m/Y') }}</td>
                                            @foreach ($column['values'] as $value)
                                                <td class="text-right tabular-nums">
                                                    {{ $value['balance'] === null ? '—' : $euros($value['balance']) }}
                                                    @if ($value['as_of'] !== null && ! $value['as_of']->isSameDay($column['day']))
                                                        <div class="text-xs text-subtle">{{ __('balance of :date', ['date' => $value['as_of']->format('d/m/Y')]) }}</div>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="text-right font-semibold tabular-nums">{{ $euros($column['total']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </x-card>

                <x-card :title="__('Income and expenses per month')" class="shadow-sm xl:col-span-2" separator data-print-keep>
                    <x-charts.monthly-flows id="chart-monthly" :months="$months" :palette="$palette"
                        :title="__('Income and expenses per month, :year', ['year' => $yearLabel])"
                        :description="__('Grouped columns for the income and the expenses of each month, and a line for the result as it builds up over the year.')"
                        :labels="['income' => __('Income'), 'expenses' => __('Expenses'), 'cumulative' => __('Cumulative result')]" />

                    <details class="mt-3 text-sm" data-print-hide>
                        <summary class="cursor-pointer text-muted">{{ __('Show the figures') }}</summary>
                        <div class="mt-2 overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Month') }}</th>
                                        <th class="text-right">{{ __('Income') }}</th>
                                        <th class="text-right">{{ __('Expenses') }}</th>
                                        <th class="text-right">{{ __('Cumulative result') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($months as $month)
                                        <tr>
                                            <td>{{ $month['long'] }}</td>
                                            <td class="text-right tabular-nums">{{ $euros($month['income']) }}</td>
                                            <td class="text-right tabular-nums">{{ $euros($month['expenses']) }}</td>
                                            <td class="text-right tabular-nums">{{ $euros($month['cumulative']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </x-card>

                <x-card :title="__('Expenses per poste')" class="shadow-sm xl:col-span-2" separator data-print-keep>
                    @if ($expenseRows === [])
                        <p class="text-sm text-muted">{{ __('No expense in either year.') }}</p>
                    @else
                        <x-charts.paired-bars id="chart-expenses" :rows="$expenseRows" :palette="$palette"
                            :current-label="$yearLabel" :previous-label="$previousLabel"
                            :title="__('Expenses per poste, :year compared with :previous', ['year' => $yearLabel, 'previous' => $previousLabel])"
                            :description="__('One row per poste, largest first: this year in colour, the year before in grey.')" />
                    @endif
                </x-card>

                <x-card :title="__('Income per poste')" class="shadow-sm xl:col-span-2" separator data-print-keep>
                    @if ($incomeRows === [])
                        <p class="text-sm text-muted">{{ __('No income in either year.') }}</p>
                    @else
                        <x-charts.paired-bars id="chart-income" :rows="$incomeRows" :palette="$palette"
                            :current-label="$yearLabel" :previous-label="$previousLabel"
                            :title="__('Income per poste, :year compared with :previous', ['year' => $yearLabel, 'previous' => $previousLabel])"
                            :description="__('One row per poste, website and off-site money together, largest first: this year in colour, the year before in grey.')" />
                    @endif
                </x-card>

                <x-card :title="__('How the movements were closed')" class="shadow-sm xl:col-span-2" separator data-print-keep>
                    <x-charts.share-bars id="chart-closure" :palette="$palette"
                        :title="__('How the movements of :year were closed', ['year' => $yearLabel])"
                        :description="__('Two 100 % bars, by number of movements and by amount: reconciled by the website, justified by a document, residue written off, internal, still to process.')"
                        :segments="array_map(fn ($closure) => ['key' => $closure->value, 'label' => $closure->label()], $closures)"
                        :rows="[
                            ['label' => __('Number'), 'values' => $justification['count'], 'format' => 'count'],
                            ['label' => __('Amount'), 'values' => $justification['amount'], 'format' => 'euros'],
                        ]" />

                    <div class="mt-3 overflow-x-auto">
                        <table class="table table-sm" data-closure-table>
                            <thead>
                                <tr>
                                    <th>{{ __('State') }}</th>
                                    <th class="text-right">{{ __('Movements') }}</th>
                                    <th class="text-right">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($closures as $closure)
                                    <tr>
                                        <td>
                                            <span class="flex items-center gap-2">
                                                <span aria-hidden="true" class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm"
                                                    style="background: {{ $palette[$closure->value] }}"></span>
                                                @if ($closure === MovementClosure::ToProcess)
                                                    <x-icon name="o-exclamation-triangle" class="h-4 w-4 text-warning-content" />
                                                @endif
                                                {{ $closure->label() }}
                                            </span>
                                        </td>
                                        <td class="text-right tabular-nums">{{ $justification['count'][$closure->value] }}</td>
                                        <td class="text-right tabular-nums">{{ $euros($justification['amount'][$closure->value]) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>

            {{-- Postes, figure by figure --}}
            <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                @foreach ([['title' => __('Expenses per poste'), 'rows' => $expenseRows, 'good' => 'down', 'total' => $flows['expenses']], ['title' => __('Income per poste'), 'rows' => $incomeRows, 'good' => 'up', 'total' => $flows['income']]] as $table)
                    <x-card :title="$table['title']" class="shadow-sm" separator data-print-keep>
                        <div class="overflow-x-auto">
                            <table class="table table-sm" data-poste-table>
                                <thead>
                                    <tr>
                                        <th>{{ __('Poste') }}</th>
                                        <th class="text-right">{{ $yearLabel }}</th>
                                        <th class="text-right">{{ $previousLabel }}</th>
                                        <th class="text-right">{{ __('Evolution') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($table['rows'] as $row)
                                        <tr @class(['text-warning-content' => $row['uncategorised']])>
                                            <td>{{ $row['label'] }}</td>
                                            <td class="text-right tabular-nums">{{ $euros($row['current']) }}</td>
                                            <td class="text-right tabular-nums">{{ $euros($row['previous']) }}</td>
                                            <td class="text-right tabular-nums">{{ ChartFormat::change($row['current'], $row['previous']) ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">{{ __('Nothing this year nor the year before.') }}</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>{{ __('Total') }}</th>
                                        <th class="text-right tabular-nums">{{ $euros($table['total'][0]) }}</th>
                                        <th class="text-right tabular-nums">{{ $euros($table['total'][1]) }}</th>
                                        <th class="text-right tabular-nums">{{ ChartFormat::change($table['total'][0], $table['total'][1]) ?? '—' }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </x-card>
                @endforeach

                <x-card :title="__('Trainings: what they bring and what they cost')" class="shadow-sm" separator data-print-keep>
                    <div class="overflow-x-auto">
                        <table class="table table-sm" data-trainings-table>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th class="text-right">{{ $yearLabel }}</th>
                                    <th class="text-right">{{ $previousLabel }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ __('Trainings') }} <span class="text-subtle">({{ __('income') }})</span></td>
                                    <td class="text-right tabular-nums">{{ $euros($trainings['income'][0]) }}</td>
                                    <td class="text-right tabular-nums">{{ $euros($trainings['income'][1]) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Training & education') }} <span class="text-subtle">({{ __('expense') }})</span></td>
                                    <td class="text-right tabular-nums">{{ $euros($trainings['expense'][0]) }}</td>
                                    <td class="text-right tabular-nums">{{ $euros($trainings['expense'][1]) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>{{ __('Balance') }}</th>
                                    <th class="text-right tabular-nums">{{ $euros($trainings['income'][0] - $trainings['expense'][0]) }}</th>
                                    <th class="text-right tabular-nums">{{ $euros($trainings['income'][1] - $trainings['expense'][1]) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </x-card>

                <x-card :title="__('Membership fees by licence')" class="shadow-sm" separator data-print-keep>
                    <div class="overflow-x-auto">
                        <table class="table table-sm" data-fees-table>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th class="text-right">{{ $yearLabel }}</th>
                                    <th class="text-right">{{ $previousLabel }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ __('Recreational') }}</td>
                                    <td class="text-right tabular-nums">{{ $euros($fees[0]['recreational']) }}</td>
                                    <td class="text-right tabular-nums">{{ $euros($fees[1]['recreational']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Competitive') }}</td>
                                    <td class="text-right tabular-nums">{{ $euros($fees[0]['competitive']) }}</td>
                                    <td class="text-right tabular-nums">{{ $euros($fees[1]['competitive']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>

            {{-- Internal movements: closed and out of the flows, shown for the record --}}
            <section class="mb-6" data-print-keep data-internal-movements>
                <h3 class="mb-2 text-xs font-bold uppercase tracking-widest text-muted">{{ __('Internal movements') }}</h3>
                <p class="mb-2 text-xs text-subtle">{{ __('Money moved between the club\'s own accounts, or between the till and the bank: neither income nor expense.') }}</p>
                @if ($internalMovements === [])
                    <p class="text-sm text-muted">{{ __('None this year.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-xs">
                            <tbody>
                                @foreach ($internalMovements as $movement)
                                    <tr>
                                        <td class="tabular-nums">{{ $movement['date']->format('d/m/Y') }}</td>
                                        <td>{{ $movement['source'] }}</td>
                                        <td>{{ $movement['description'] }}</td>
                                        <td class="text-right tabular-nums">{{ $euros($movement['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </x-tab>

        {{-- ── Documents & exports ────────────────────────────────────────── --}}
        <x-tab name="pieces" :label="__('Documents & exports')" icon="o-document-duplicate">
            {{-- The export of the year — a PDF for the assembly, a ZIP of the
                 originals for the archive — above the lists it will contain.
                 Prepared in the background; the link arrives by mail, in the
                 bell and under « Mes exports ». --}}
            <x-card :title="__('Export :year', ['year' => $yearLabel])" class="mb-6 shadow-sm" separator data-print-hide data-export-form>
                <p class="mb-4 text-sm text-muted">{{ __('The report, the journal of every movement, then the pieces: printed in the PDF, as originals in the ZIP.') }}</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-select :label="__('Poste')" wire:model="exportPoste" :options="$this->posteOptions()"
                        :placeholder="__('Every poste')" />
                    @php $scopes = \App\Domains\Shared\Enums\FinancialExportScope::offered(); @endphp
                    @if (count($scopes) > 1)
                        <x-select :label="__('Pieces')" wire:model="exportScope"
                            :options="array_map(fn ($scope) => ['id' => $scope->value, 'name' => $scope->label()], $scopes)" />
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button :label="__('Printable PDF')" icon="o-printer" class="btn-primary btn-sm" wire:click="export('pdf')" spinner="export" />
                    <x-button :label="__('ZIP archive (originals)')" icon="o-archive-box-arrow-down" class="btn-sm" wire:click="export('zip')" spinner="export" />
                </div>

                {{-- Whoever's ZIP download archives the expense reports: where
                     paid reports still wait for it. --}}
                @if ($this->unarchivedByYear !== [])
                    <div class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm" data-unarchived>
                        <x-icon name="o-archive-box" class="me-1 size-4 text-warning-content" />
                        {{ __('Paid expense reports not archived yet:') }}
                        @foreach ($this->unarchivedByYear as $start => $count)
                            @php $label = \App\Domains\Shared\ValueObjects\FiscalYear::startingIn($start)->label(); @endphp
                            @if ($label === $yearLabel)
                                <strong>{{ trans_choice(':count in :year|:count in :year', $count, ['year' => $label]) }}</strong>
                            @else
                                <a class="link" href="{{ route('admin.treasury.report', ['year' => $start, 'tab' => 'pieces']) }}" wire:navigate>{{ trans_choice(':count in :year|:count in :year', $count, ['year' => $label]) }}</a>
                            @endif
                            @unless ($loop->last) · @endunless
                        @endforeach
                        <div class="mt-1 text-xs text-muted">{{ __('Downloading the ZIP of a year archives its expense reports.') }}</div>
                    </div>
                @endif
            </x-card>

            {{-- Mes exports : la cloche ne se rafraîchit pas d'elle-même, et rien
                 d'autre ne menait au fichier une fois prêt. L'onglet interroge le
                 serveur tant qu'un export se prépare, puis s'arrête. --}}
            @if ($this->myExports->isNotEmpty())
                @php $exportsPending = $this->myExports->contains('status', 'pending'); @endphp
                <div class="mb-6 rounded-xl border border-base-300 bg-base-100" data-my-exports data-print-hide
                    @if ($exportsPending) wire:poll.3s @endif>
                    <p class="border-b border-base-300 px-4 py-2 text-xs font-bold uppercase tracking-widest text-muted">
                        {{ __('My exports') }}
                    </p>
                    <div class="divide-y divide-base-200">
                        @foreach ($this->myExports as $export)
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-2 text-sm" wire:key="export-{{ $export->id }}">
                                <x-icon :name="$export->isZip() ? 'o-archive-box-arrow-down' : 'o-printer'" class="h-5 w-5 shrink-0 text-muted" />
                                <div class="min-w-0 flex-1">
                                    <span class="font-semibold">{{ $export->isZip() ? __('ZIP archive') : __('Printable PDF') }}</span>
                                    <span class="text-muted">
                                        · {{ $export->summary() }}
                                        · {{ $export->created_at?->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                                @if ($export->status === 'pending')
                                    <span class="flex items-center gap-2 text-muted">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        {{ __('Being prepared…') }}
                                    </span>
                                @elseif ($export->status === 'failed')
                                    <x-badge :value="__('Failed')" class="badge-error badge-soft badge-sm" />
                                    @if ($export->fiscal_year !== null)
                                        <x-button :label="__('Run it again')" icon="o-arrow-path" class="btn-ghost btn-sm"
                                            wire:click="retryExport({{ $export->id }})" spinner="retryExport({{ $export->id }})" />
                                    @endif
                                @elseif ($export->isExpired())
                                    <span class="text-muted">{{ __('Expired') }}</span>
                                @else
                                    <x-button :label="__('Download')" icon="o-arrow-down-tray" class="btn-primary btn-sm"
                                        :link="route('admin.treasury.exports.download', $export)" no-wire-navigate />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <p class="mb-4 text-sm text-muted">{{ __('Everything behind the figures of :year: the supporting documents dated in the year or paid in it, the expense reports and the website payments whose money moved in it.', ['year' => $yearLabel]) }}</p>

            @php
                $pieces = [
                    'documents' => $this->pieces->documents(),
                    'expenseReports' => $this->pieces->expenseReports(),
                    'sitePayments' => $this->pieces->sitePayments(),
                ];
            @endphp

            <x-card :title="__('Supporting documents')" class="mb-6 shadow-sm" separator>
                @if ($pieces['documents']->isEmpty())
                    <p class="text-sm text-muted">{{ __('No supporting document dated in this year.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm" data-pieces-documents>
                            <thead>
                                <tr>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Counterparty') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-right">{{ __('Amount') }}</th>
                                    <th>{{ __('State') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pieces['documents'] as $document)
                                    @php $state = $document->state(); @endphp
                                    <tr wire:key="piece-document-{{ $document->id }}">
                                        <td class="font-mono text-xs">
                                            @can('view', $document)
                                                <a class="link link-hover" href="{{ route('admin.treasury.supporting-documents', ['document' => $document->id]) }}" wire:navigate>{{ $document->reference() }}</a>
                                            @else
                                                {{ $document->reference() }}
                                            @endcan
                                        </td>
                                        <td class="tabular-nums">{{ $document->date->format('d/m/Y') }}</td>
                                        <td>
                                            <div class="font-medium">{{ $document->counterparty }}</div>
                                            <div class="text-xs text-muted">{{ $document->label }}</div>
                                        </td>
                                        <td>{{ $document->category()->label() }}</td>
                                        <td class="text-right tabular-nums">{{ $document->isExpense() ? '−' : '+' }}{{ $euros($document->amount) }}</td>
                                        <td><x-badge :value="$state->label()" class="badge-sm {{ $state->badgeClass() }}" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            @feature('expense_reports')
                <x-card :title="__('Expense reports')" class="mb-6 shadow-sm" separator>
                    @if ($pieces['expenseReports'] === [])
                        <p class="text-sm text-muted">{{ __('No expense report paid in this year.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table table-sm" data-pieces-expense-reports>
                                <thead>
                                    <tr>
                                        <th>{{ __('Paid on') }}</th>
                                        <th>{{ __('Member') }}</th>
                                        <th>{{ __('Category') }}</th>
                                        <th class="text-right">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pieces['expenseReports'] as $paid)
                                        <tr wire:key="piece-report-{{ $paid['report']->id }}">
                                            <td class="tabular-nums">{{ $paid['date']->format('d/m/Y') }}</td>
                                            <td>{{ $paid['report']->user?->full_name }}</td>
                                            <td>
                                                <div>{{ $paid['report']->category->label() }}</div>
                                                <div class="text-xs text-muted">{{ $paid['report']->description }}</div>
                                            </td>
                                            <td class="text-right tabular-nums">{{ $euros($paid['amount']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            @endfeature

            <x-card :title="__('Website payments')" class="mb-6 shadow-sm" separator>
                @if ($pieces['sitePayments'] === [])
                    <p class="text-sm text-muted">{{ __('No website payment reconciled in this year.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm" data-pieces-site-payments>
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Account or till') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th>{{ __('Poste') }}</th>
                                    <th class="text-right">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pieces['sitePayments'] as $payment)
                                    <tr wire:key="piece-payment-{{ $payment['kind'] }}-{{ $payment['id'] }}">
                                        <td class="tabular-nums">{{ $payment['date']->format('d/m/Y') }}</td>
                                        <td>{{ $payment['source'] }}</td>
                                        <td>
                                            <div>{{ $payment['counterparty'] ?? $payment['description'] }}</div>
                                            @if ($payment['counterparty'])
                                                <div class="text-xs text-muted">{{ $payment['description'] }}</div>
                                            @endif
                                        </td>
                                        <td>{{ implode(', ', array_map(FinancialReport::posteLabel(...), $payment['postes'])) }}</td>
                                        <td class="text-right tabular-nums">{{ $euros($payment['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </x-tab>
    </x-tabs>
</div>
