<?php

declare(strict_types=1);

namespace App\Domains\Shared\ValueObjects;

use App\Domains\Competitions\Interclub\Models\Club;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The club's financial year, the span the accounts are closed on.
 *
 * It starts on the first day of the month set on the club record
 * (`fiscal_year_start_month`, January when unset) and lasts twelve months.
 * A year that starts in January is the calendar year and is named « 2026 »;
 * any other one straddles two calendar years and is named « 2025-2026 ».
 */
final readonly class FiscalYear
{
    private function __construct(private CarbonImmutable $start) {}

    /**
     * The financial year running today.
     */
    public static function current(): self
    {
        return self::for(CarbonImmutable::now());
    }

    /**
     * The financial year the given day belongs to.
     */
    public static function for(CarbonInterface $date): self
    {
        $startMonth = self::startMonth();
        $day = CarbonImmutable::parse($date->toDateString());
        $startYear = $day->month >= $startMonth ? $day->year : $day->year - 1;

        return new self(CarbonImmutable::create($startYear, $startMonth, 1));
    }

    /**
     * The financial year that starts in the given calendar year — the number
     * a screen carries in its URL to name a year.
     */
    public static function startingIn(int $year): self
    {
        return new self(CarbonImmutable::create($year, self::startMonth(), 1));
    }

    /**
     * The month the club's financial year starts in, 1 for January.
     */
    public static function startMonth(): int
    {
        return (int) (Club::own()?->fiscal_year_start_month ?? 1);
    }

    /**
     * « 2026 » for a calendar year, « 2627 » for one that straddles two — the
     * label squeezed into a document number.
     */
    public function code(): string
    {
        return $this->start->month === 1
            ? (string) $this->start->year
            : sprintf('%02d%02d', $this->start->year % 100, ($this->start->year + 1) % 100);
    }

    /**
     * The last day of the year, at the start of that day.
     */
    public function end(): CarbonImmutable
    {
        return $this->start->addYear()->subDay();
    }

    /**
     * « 2026 » for a calendar year, « 2025-2026 » for one that straddles two.
     */
    public function label(): string
    {
        return $this->start->month === 1
            ? (string) $this->start->year
            : $this->start->year . '-' . ($this->start->year + 1);
    }

    public function previous(): self
    {
        return new self($this->start->subYear());
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    /**
     * The calendar year the financial year starts in, see {@see self::startingIn()}.
     */
    public function startYear(): int
    {
        return $this->start->year;
    }
}
