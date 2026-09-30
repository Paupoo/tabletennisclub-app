<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Illuminate\Support\Carbon;

describe('a club whose financial year is the calendar year', function (): void {
    it('runs from the first of January to the thirty-first of December, named by its year', function (): void {
        Club::factory()->ownClub()->create();

        $year = FiscalYear::for(Carbon::parse('2026-09-29'));

        expect($year->start()->toDateString())->toBe('2026-01-01')
            ->and($year->end()->toDateString())->toBe('2026-12-31')
            ->and($year->label())->toBe('2026');
    });
});

describe('a club whose financial year starts in September', function (): void {
    beforeEach(function (): void {
        Club::factory()->ownClub()->create(['fiscal_year_start_month' => 9]);
    });

    it('straddles two calendar years and is named after both', function (): void {
        $year = FiscalYear::for(Carbon::parse('2026-09-29'));

        expect($year->start()->toDateString())->toBe('2026-09-01')
            ->and($year->end()->toDateString())->toBe('2027-08-31')
            ->and($year->label())->toBe('2026-2027');
    });

    it('puts the last day of August in the year that started the September before', function (): void {
        expect(FiscalYear::for(Carbon::parse('2026-08-31'))->label())->toBe('2025-2026');
    });

    it('steps back to the year before', function (): void {
        expect(FiscalYear::for(Carbon::parse('2026-09-29'))->previous()->label())->toBe('2025-2026');
    });

    it('is the one running today', function (): void {
        $this->travelTo(Carbon::parse('2027-02-10'));

        expect(FiscalYear::current()->label())->toBe('2026-2027');
    });

    it('is found by the calendar year it starts in', function (): void {
        $year = FiscalYear::startingIn(2025);

        expect($year->label())->toBe('2025-2026')
            ->and($year->startYear())->toBe(2025);
    });
});
