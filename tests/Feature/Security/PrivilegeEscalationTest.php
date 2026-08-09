<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
| `is_admin` used to sit in $fillable, on the users table. Any code path reaching
| User::create() or ->update() with unfiltered input could therefore hand out
| administrator rights. Nothing exploited it, but the door was open.
|
| The column is gone: rights live in a pivot table that mass assignment cannot
| reach at all. These tests assert the property that replaced it — a role is only
| ever granted by an explicit call — so that reintroducing a writable flag, or a
| form field that assigns roles from raw input, shows up here.
*/

it('cannot be granted a role through creation attributes', function (): void {
    $created = User::create([
        'first_name' => 'Mallory',
        'last_name' => 'Test',
        'email' => 'mallory@example.test',
        'password' => 'secret-password',
        'is_admin' => true,
        'roles' => [Role::ADMINISTRATOR->value],
    ]);

    expect($created->fresh()->hasRole(Role::ADMINISTRATOR->value))->toBeFalse();
});

it('cannot be granted a role through an update', function (): void {
    $user = User::factory()->create();

    $user->update(['is_admin' => true, 'roles' => [Role::ADMINISTRATOR->value]]);

    expect($user->fresh()->getRoleNames())->toBeEmpty();
});

it('no longer keeps the retired flags on the table', function (string $column): void {
    expect(Schema::hasColumn('users', $column))->toBeFalse();
})->with(['is_admin', 'is_committee_member', 'is_coach', 'is_selector']);

it('only grants a role through an explicit assignment', function (): void {
    $user = User::factory()->create();
    expect($user->hasRole(Role::ADMINISTRATOR->value))->toBeFalse();

    $user->assignRole(Role::ADMINISTRATOR->value);

    expect($user->fresh()->hasRole(Role::ADMINISTRATOR->value))->toBeTrue();
});
