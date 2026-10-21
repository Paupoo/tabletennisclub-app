<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Carbon\CarbonImmutable;

/*
 * The factory drew birthdates from the real clock while tests move it: a test
 * set in March 2026 could get an "adult" born in June 2008, aged 17 on the
 * test's day, a minor with no guardian sent to the onboarding wizard (302
 * instead of 403, about one run in a hundred).
 */

it('makes an adult on the day the test is set, wherever the clock was moved', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-03-15 12:00'));

    $birthdates = collect(range(1, 200))->map(fn (): mixed => User::factory()->make()->birthdate);

    expect($birthdates->every(fn (mixed $birthdate): bool => CarbonImmutable::parse($birthdate)->age >= 18))->toBeTrue();
});

it('makes a minor on the day the test is set, wherever the clock was moved', function (): void {
    $this->travelTo(CarbonImmutable::parse('2030-03-15 12:00'));

    $birthdates = collect(range(1, 200))->map(fn (): mixed => User::factory()->minor()->make()->birthdate);

    expect($birthdates->every(fn (mixed $birthdate): bool => CarbonImmutable::parse($birthdate)->age < 18))->toBeTrue();
});
