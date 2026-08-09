<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission as PermissionEnum;
use App\Domains\Shared\Enums\Role as RoleEnum;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
| The seeder is meant to run on every deploy: it is what keeps the database
| aligned with the matrix held in Git. That only works if it is idempotent and
| authoritative — it must converge to the matrix from any prior state.
*/

it('creates every role and permission declared by the matrix', function (): void {
    (new RoleSeeder)->run();

    expect(Role::count())->toBe(count(RoleEnum::cases()))
        ->and(Permission::count())->toBe(count(PermissionEnum::cases()));
});

it('is idempotent', function (): void {
    (new RoleSeeder)->run();
    $before = [Role::count(), Permission::count(), DB::table('role_has_permissions')->count()];

    (new RoleSeeder)->run();

    expect([Role::count(), Permission::count(), DB::table('role_has_permissions')->count()])
        ->toBe($before);
});

it('grants each role exactly the permissions the matrix declares', function (RoleEnum $roleEnum): void {
    (new RoleSeeder)->run();

    $expected = array_map(
        static fn (PermissionEnum $p): string => $p->value,
        $roleEnum->permissions(),
    );

    $actual = Role::findByName($roleEnum->value, 'web')
        ->permissions
        ->pluck('name')
        ->all();

    sort($expected);
    sort($actual);

    expect($actual)->toBe($expected);
})->with(RoleEnum::cases());

it('prunes a role the matrix no longer declares, and its assignments', function (): void {
    (new RoleSeeder)->run();

    $obsolete = Role::create(['name' => 'ancienne-delegation', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($obsolete->name);

    (new RoleSeeder)->run();

    expect(Role::where('name', 'ancienne-delegation')->exists())->toBeFalse()
        ->and($user->fresh()->getRoleNames())->not->toContain('ancienne-delegation');
});

it('prunes a permission the matrix no longer declares', function (): void {
    (new RoleSeeder)->run();

    Permission::create(['name' => 'ancien.droit', 'guard_name' => 'web']);

    (new RoleSeeder)->run();

    expect(Permission::where('name', 'ancien.droit')->exists())->toBeFalse();
});

it('leaves role assignments alone — those belong to the admin UI', function (): void {
    (new RoleSeeder)->run();

    $user = User::factory()
        ->withRole(RoleEnum::CASH_REGISTER, RoleEnum::WEBSITE)
        ->create();

    (new RoleSeeder)->run();

    expect($user->fresh()->getRoleNames()->all())
        ->toContain(RoleEnum::CASH_REGISTER->value)
        ->toContain(RoleEnum::WEBSITE->value);
});
