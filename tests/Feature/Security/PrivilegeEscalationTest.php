<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| `is_admin` used to sit in $fillable. Any code path reaching User::create() or
| ->update() with unfiltered input could therefore hand out administrator rights.
| Nothing exploited it, but the door was open; these tests keep it shut.
*/

it('refuses to mass-assign the retired role flags', function (string $flag): void {
    $user = User::factory()->create();

    $user->update([$flag => true]);

    // Two independent guards, asserted separately so a regression in either shows:
    // the attribute is no longer $fillable (the column stays untouched), and the
    // accessor answers from the roles regardless of what the column says.
    expect(DB::table('users')->where('id', $user->id)->value($flag))
        ->toBeIn([0, false], "La colonne {$flag} a été écrite en mass-assignment.")
        ->and($user->fresh()->{$flag})->toBeFalse();
})->with(['is_admin', 'is_committee_member', 'is_coach', 'is_selector']);

it('ignores the legacy column even if something writes it directly', function (): void {
    $user = User::factory()->create();

    DB::table('users')->where('id', $user->id)->update(['is_admin' => true]);

    expect($user->fresh()->is_admin)->toBeFalse();
});

it('refuses to grant administrator through creation attributes', function (): void {
    $user = User::factory()->create();
    $created = User::create([
        'first_name' => 'Mallory',
        'last_name' => 'Test',
        'email' => 'mallory@example.test',
        'password' => 'secret-password',
        'is_admin' => true,
    ]);

    expect($created->fresh()->is_admin)->toBeFalse()
        ->and($user->fresh()->is_admin)->toBeFalse();
});

it('only grants a role through an explicit assignment', function (): void {
    $user = User::factory()->create();
    expect($user->is_admin)->toBeFalse();

    $user->assignRole(Role::ADMINISTRATOR->value);

    expect($user->fresh()->is_admin)->toBeTrue();
});
