<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Services;

use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Carbon\CarbonImmutable;

/**
 * The financial report laid out for reading: every figure the screen and
 * the PDF of the general assembly show, from one {@see FinancialReport}, the
 * year it is compared with, and the {@see FinancialPosition} of the club.
 *
 * Neither the screen nor the PDF computes a figure of its own: both render
 * this array, so they never disagree.
 */
final class FinancialReportFigures
{
    /**
     * @return array<string, mixed>
     */
    public static function of(FinancialReport $report, FinancialReport $previous, FinancialPosition $position, ?CarbonImmutable $today = null): array
    {
        $year = $report->year();
        $treasuryDay = ($today ?? CarbonImmutable::today())->min($year->end());

        return [
            'yearLabel' => $year->label(),
            'yearStart' => $year->start()->toDateString(),
            'yearEnd' => $year->end()->toDateString(),
            'previousLabel' => $previous->cutOffAt() === null
                ? $year->previous()->label()
                : __(':year at the same date', ['year' => $year->previous()->label()]),
            'flows' => [
                'income' => [$report->income(), $previous->income()],
                'expenses' => [$report->expenses(), $previous->expenses()],
                'result' => [$report->result(), $previous->result()],
            ],
            'justification' => $report->justification(),
            'members' => $position->membersWithOpenDebt(),
            'receivables' => $position->openReceivables(),
            'debts' => $position->openDebts(),
            'treasury' => $position->treasurySummary($year, $treasuryDay),
            'yearStartLabel' => __('the 1st of :month', ['month' => $year->start()->translatedFormat('F')]),
            'holdings' => self::holdings($position, $year, $treasuryDay),
            'treasuryDay' => $treasuryDay,
            'months' => self::months($report),
            'expenseRows' => self::posteRows($report->expensesByCategory(), $previous->expensesByCategory(), 'expense'),
            'incomeRows' => self::posteRows($report->incomeByCategory(), $previous->incomeByCategory(), 'income'),
            'trainings' => [
                'income' => [$report->incomeByCategory()[IncomeCategory::Trainings->value] ?? 0.0, $previous->incomeByCategory()[IncomeCategory::Trainings->value] ?? 0.0],
                'expense' => [$report->expensesByCategory()[ExpenseCategory::Training->value] ?? 0.0, $previous->expensesByCategory()[ExpenseCategory::Training->value] ?? 0.0],
            ],
            'fees' => [$report->membershipFeesByLicence(), $previous->membershipFeesByLicence()],
            'internalMovements' => $report->internalMovements(),
        ];
    }

    /**
     * The money held at each month end, for the treasury chart: bank
     * accounts in the categorical order, the tills on top in their own
     * colour.
     *
     * @return array{series: list<array{key: string, label: string, role: string}>, columns: list<array<string, mixed>>}
     */
    private static function holdings(FinancialPosition $position, FiscalYear $year, CarbonImmutable $until): array
    {
        $history = $position->treasuryByMonth($year, $until);
        $accountRoles = ['holding_1', 'holding_2', 'holding_3'];
        $series = [];

        foreach ($history['series'] as $index => $serie) {
            $series[] = [...$serie, 'role' => $serie['key'] === 'cash' ? 'holding_cash' : $accountRoles[min($index, 2)]];
        }

        return [
            'series' => $series,
            'columns' => array_map(static fn (array $month): array => [
                ...$month,
                'label' => $month['day']->translatedFormat('M'),
                'long' => $month['day']->isLastOfMonth()
                    ? ucfirst($month['day']->translatedFormat('F Y'))
                    : $month['day']->translatedFormat('j F Y'),
            ], $history['months']),
        ];
    }

    /**
     * The months of the year for the chart. A month not reached yet, with
     * nothing booked in it nor after it, has no result to draw: the line
     * stops there instead of running flat to December.
     *
     * @return list<array{label: string, long: string, income: float, expenses: float, cumulative: float, future: bool}>
     */
    private static function months(FinancialReport $report): array
    {
        $months = [];
        $quietFromHere = true;

        foreach (array_reverse($report->monthly()) as $month) {
            $quietFromHere = $quietFromHere && $month['income'] === 0.0 && $month['expenses'] === 0.0;

            $months[] = [
                'label' => $month['month']->translatedFormat('M'),
                'long' => ucfirst($month['month']->translatedFormat('F Y')),
                'income' => $month['income'],
                'expenses' => $month['expenses'],
                'cumulative' => $month['cumulative'],
                'future' => $quietFromHere && $month['month']->greaterThan(CarbonImmutable::today()),
            ];
        }

        return array_reverse($months);
    }

    /**
     * One row per poste either year saw, largest this year first,
     * uncategorised last whatever its size — it is a to-do, not a poste.
     *
     * @param  array<string, float>  $current
     * @param  array<string, float>  $previous
     * @return list<array{key: string, label: string, current: float, previous: float, uncategorised: bool}>
     */
    private static function posteRows(array $current, array $previous, string $direction): array
    {
        $rows = [];

        foreach (array_unique([...array_keys($current), ...array_keys($previous)]) as $key) {
            $category = $direction === 'expense' ? ExpenseCategory::tryFrom($key) : IncomeCategory::tryFrom($key);

            $rows[] = [
                'key' => $key,
                'label' => $category?->label() ?? __('Uncategorised — to process'),
                'current' => $current[$key] ?? 0.0,
                'previous' => $previous[$key] ?? 0.0,
                'uncategorised' => $key === FinancialReport::UNCATEGORISED,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['uncategorised'], -$a['current'], -$a['previous']] <=> [$b['uncategorised'], -$b['current'], -$b['previous']]);

        return $rows;
    }
}
