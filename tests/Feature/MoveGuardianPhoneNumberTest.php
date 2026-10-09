<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;

/*
 * `users.guardian_phone_number` held the number of whoever answers for a minor,
 * beside the guardian sheets that hold it too. The number moves onto the sheet;
 * a minor nobody is named for keeps it, so it is not lost while the office
 * records their guardian.
 */

function runMoveGuardianPhoneNumberMigration(): void
{
    $migration = require base_path('database/migrations/2026_10_08_212024_move_guardian_phone_number_to_guardian_sheets.php');
    $migration->up();
}

it('moves the number onto a guardian sheet that has none', function (): void {
    $child = User::factory()->create(['guardian_phone_number' => '0475111222']);
    $guardian = Guardian::factory()->create(['phone' => '']);
    $child->guardians()->attach($guardian);

    runMoveGuardianPhoneNumberMigration();

    expect($guardian->fresh()->phone)->toBe('0475111222')
        ->and($child->fresh()->guardian_phone_number)->toBeNull();
});

it('keeps the number the sheet already holds', function (): void {
    $child = User::factory()->create(['guardian_phone_number' => '0475111222']);
    $guardian = Guardian::factory()->create(['phone' => '0475999888']);
    $child->guardians()->attach($guardian);

    runMoveGuardianPhoneNumberMigration();

    expect($guardian->fresh()->phone)->toBe('0475999888')
        ->and($child->fresh()->guardian_phone_number)->toBeNull();
});

it('keeps the number of a minor nobody is named for', function (): void {
    $child = User::factory()->create(['guardian_phone_number' => '0475111222']);

    runMoveGuardianPhoneNumberMigration();

    expect($child->fresh()->guardian_phone_number)->toBe('0475111222');
});

it('moves every number, however many there are', function (): void {
    $children = User::factory()->count(3)->create(['guardian_phone_number' => '0475111222'])
        ->each(fn (User $child) => $child->guardians()->attach(Guardian::factory()->create(['phone' => ''])));

    runMoveGuardianPhoneNumberMigration();

    expect(User::whereNotNull('guardian_phone_number')->count())->toBe(0)
        ->and($children->every(fn (User $child): bool => $child->guardians()->first()->phone === '0475111222'))->toBeTrue();
});
