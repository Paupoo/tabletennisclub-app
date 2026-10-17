<?php

declare(strict_types=1);

namespace App\Data\Attestation;

use Illuminate\Support\Carbon;

/**
 * The days of a training camp a member took part in, as the club certifies them.
 *
 * The insurers pay by the day, so this figure is the one that moves money. A
 * day is a calendar date on which a session was held, not one the member was
 * marked present on: the forms ask about taking part in the camp, and a child
 * home with a fever on the Wednesday was still enrolled on it.
 *
 * A date is a half day when its sessions span less than four hours, from the
 * first start to the last end — the span rather than the sum, so a 9 to 16 day
 * with a lunch break is a full day and not six hours.
 */
final readonly class CampDays
{
    /** Below this many minutes from first start to last end, a date is a half day. */
    public const int HALF_DAY_BELOW_MINUTES = 240;

    /**
     * @param  array<int, array{date: Carbon, half: bool}>  $dates  In chronological order.
     */
    public function __construct(public array $dates) {}

    /**
     * « 5 jours », « 3 demi-jours », « 4 jours et 1 demi-jour ».
     *
     * Static as well, for a certificate already issued: the row keeps the two
     * counts it stated, not the dates they came from.
     */
    public static function describe(int $fullDays, int $halfDays): string
    {
        $parts = array_filter([
            $fullDays > 0 ? trans_choice(':count day|:count days', $fullDays, ['count' => $fullDays]) : null,
            $halfDays > 0 ? trans_choice(':count half day|:count half days', $halfDays, ['count' => $halfDays]) : null,
        ]);

        return implode(' ' . __('and') . ' ', $parts);
    }

    /**
     * Whether a form that offers « jours / demi-jour » must be answered in half days.
     *
     * Only when there is at least one. A mixed camp is counted in half days
     * because a day is exactly two of them, whereas rounding the halves up to
     * days would certify more than took place.
     */
    public function countsInHalfDays(): bool
    {
        return $this->halfDays() > 0;
    }

    /**
     * « 20/10/2026, 21/10/2026 et 22/10/2026 (demi-jour) ».
     *
     * One line on the club's own attestation, so that a figure the insurer pays
     * by the day can be checked against the calendar it came from.
     */
    public function datesLabel(): string
    {
        $labels = array_map(
            fn (array $date): string => $date['date']->format('d/m/Y') . ($date['half'] ? ' (' . __('half day') . ')' : ''),
            $this->dates,
        );

        if (count($labels) <= 1) {
            return implode('', $labels);
        }

        $last = array_pop($labels);

        return implode(', ', $labels) . ' ' . __('and') . ' ' . $last;
    }

    public function first(): ?Carbon
    {
        return $this->dates[0]['date'] ?? null;
    }

    public function fullDays(): int
    {
        return count(array_filter($this->dates, fn (array $date): bool => ! $date['half']));
    }

    public function halfDays(): int
    {
        return count(array_filter($this->dates, fn (array $date): bool => $date['half']));
    }

    /**
     * The figure a form offering a single unit wants, in that unit.
     */
    public function inFormUnits(): int
    {
        return $this->countsInHalfDays()
            ? $this->fullDays() * 2 + $this->halfDays()
            : $this->fullDays();
    }

    public function isEmpty(): bool
    {
        return $this->dates === [];
    }

    public function label(): string
    {
        return self::describe($this->fullDays(), $this->halfDays());
    }

    public function last(): ?Carbon
    {
        return $this->dates === [] ? null : $this->dates[count($this->dates) - 1]['date'];
    }

    /**
     * How many dates there were — what a form asking for « jours de participation » wants.
     */
    public function participationDays(): int
    {
        return count($this->dates);
    }
}
