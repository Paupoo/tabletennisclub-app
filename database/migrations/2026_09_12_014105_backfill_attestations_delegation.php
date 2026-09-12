<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives the sitting secretary the délégation the attestations need.
 *
 * `RoleSeeder` builds the role → permission matrix but never assigns a role to
 * anybody — that is the admin UI's job. Without this, the délégation would
 * land in production with no holder at all: the feature flag could be on, the
 * screens live, and the one person whose job this is locked out behind a 404.
 *
 * The seat, not the délégation, is the criterion, and only for this one-off
 * handover: `committee_role` never decides an access afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::findOrCreate(RoleEnum::ATTESTATIONS->value, 'web');

        $role->syncPermissions(array_map(
            static fn ($permission): string => $permission->value,
            RoleEnum::ATTESTATIONS->permissions(),
        ));

        DB::table('users')
            ->where('committee_role', CommitteeRolesEnum::SECRETARY->value)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function (object $user) use ($role): void {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $role->id,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ]);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
