<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Shared\Enums\Permission as PermissionEnum;
use App\Domains\Shared\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Applies the role → permission matrix held in {@see RoleEnum}.
 *
 * Idempotent and authoritative: it creates what is missing, and prunes what the
 * matrix no longer declares, so re-running it after every deploy keeps the
 * database aligned with the versioned source of truth. Role *assignments* to
 * users are untouched — those live in the admin UI.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value, 'web');

            $role->syncPermissions(array_map(
                static fn (PermissionEnum $permission): string => $permission->value,
                $roleEnum->permissions(),
            ));
        }

        $this->prune();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Removes roles and permissions dropped from the matrix, so a rename does not
     * silently leave the old entry — and its assignments — behind.
     */
    private function prune(): void
    {
        Role::query()
            ->whereNotIn('name', array_column(RoleEnum::cases(), 'value'))
            ->each(static fn (Role $role) => $role->delete());

        Permission::query()
            ->whereNotIn('name', PermissionEnum::values())
            ->each(static fn (Permission $permission) => $permission->delete());
    }
}
