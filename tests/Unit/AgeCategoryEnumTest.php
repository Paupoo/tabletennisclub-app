<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\AgeCategoryEnum;
use Illuminate\Support\Carbon;

it('exposes its values', function (): void {
    expect(AgeCategoryEnum::values())
        ->toHaveCount(3)
        ->toEqualCanonicalizing(['CHILD', 'TEEN', 'ADULT']);
});

it('provides a translated label for each case', function (): void {
    foreach (AgeCategoryEnum::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }
});

it('derives a child for someone under 13', function (): void {
    Carbon::setTestNow('2026-06-15');

    expect(AgeCategoryEnum::fromBirthdate(Carbon::parse('2014-06-16')))
        ->toBe(AgeCategoryEnum::CHILD); // 11 (turns 12 the day after)

    Carbon::setTestNow();
});

it('treats the 13th birthday as a teen', function (): void {
    Carbon::setTestNow('2026-06-15');

    expect(AgeCategoryEnum::fromBirthdate(Carbon::parse('2013-06-15')))
        ->toBe(AgeCategoryEnum::TEEN); // exactly 13 today

    Carbon::setTestNow();
});

it('keeps a 17 year old as a teen', function (): void {
    Carbon::setTestNow('2026-06-15');

    expect(AgeCategoryEnum::fromBirthdate(Carbon::parse('2009-06-15')))
        ->toBe(AgeCategoryEnum::TEEN); // exactly 17 today

    Carbon::setTestNow();
});

it('treats the 18th birthday as an adult', function (): void {
    Carbon::setTestNow('2026-06-15');

    expect(AgeCategoryEnum::fromBirthdate(Carbon::parse('2008-06-15')))
        ->toBe(AgeCategoryEnum::ADULT); // exactly 18 today

    Carbon::setTestNow();
});

it('accepts a plain DateTime instance', function (): void {
    Carbon::setTestNow('2026-06-15');

    expect(AgeCategoryEnum::fromBirthdate(new DateTime('1990-01-01')))
        ->toBe(AgeCategoryEnum::ADULT);

    Carbon::setTestNow();
});
