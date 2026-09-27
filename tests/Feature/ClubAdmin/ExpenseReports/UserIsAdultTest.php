<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-26 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('is adult on the day of the eighteenth birthday', function (): void {
    $member = User::factory()->make(['birthdate' => '2008-09-26']);

    expect($member->isAdult())->toBeTrue();
});

it('is not adult the day before the eighteenth birthday', function (): void {
    $member = User::factory()->make(['birthdate' => '2008-09-27']);

    expect($member->isAdult())->toBeFalse();
});

it('is not adult when the birthdate is unknown', function (): void {
    $member = User::factory()->make(['birthdate' => null]);

    expect($member->isAdult())->toBeFalse()
        ->and($member->isMinor())->toBeFalse();
});
