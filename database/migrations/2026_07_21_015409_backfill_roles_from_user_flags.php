<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role as RoleEnum;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Moves the four boolean flags and the statutory title over to Spatie roles.
 *
 * Access must come out unchanged: whoever reaches a page today still reaches it
 * afterwards. The statutory title therefore *seeds* the matching délégations here
 * — the one and only time it confers anything. From now on it merely displays,
 * and délégations are handed over explicitly in the admin UI.
 */
return new class extends Migration
{
    public function down(): void
    {
        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->delete();
    }

    public function up(): void
    {
        (new RoleSeeder)->run();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleIds = Role::query()->pluck('id', 'name');

        $flagToRole = [
            'is_admin' => RoleEnum::ADMINISTRATOR,
            'is_committee_member' => RoleEnum::COMMITTEE,
            'is_coach' => RoleEnum::COACH,
            'is_selector' => RoleEnum::SELECTIONS,
        ];

        $rows = [];

        DB::table('users')
            ->select(['id', 'is_admin', 'is_committee_member', 'is_coach', 'is_selector', 'committee_role'])
            ->orderBy('id')
            ->chunk(200, function ($users) use (&$rows, $roleIds, $flagToRole): void {
                foreach ($users as $user) {
                    $roles = [];

                    foreach ($flagToRole as $flag => $role) {
                        if ((bool) $user->{$flag}) {
                            $roles[] = $role;
                        }
                    }

                    $committeeRole = $user->committee_role
                        ? CommitteeRolesEnum::tryFrom((string) $user->committee_role)
                        : null;

                    if ($committeeRole instanceof CommitteeRolesEnum) {
                        $roles = array_merge($roles, self::legacyDelegationsFor($committeeRole));
                    }

                    foreach (array_unique($roles, SORT_REGULAR) as $role) {
                        $rows[] = [
                            'role_id' => $roleIds[$role->value],
                            'model_type' => User::class,
                            'model_id' => $user->id,
                        ];
                    }
                }
            });

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('model_has_roles')->insertOrIgnore($chunk);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reproduces the access each statutory title used to confer, so nobody loses
     * a page on deploy day.
     *
     * Deliberately NOT {@see RoleEnum::suggestedFor()}: that one expresses what an
     * admin would sensibly hand over today, this one mirrors the legacy helpers
     * exactly — canManageClubAdmin() (contacts + seasons), canManageFinances()
     * (fines), canViewAuditLog() (supervision), and the treasurer-only checks
     * hard-coded in the cash register and the dashboard.
     *
     * @return array<int, RoleEnum>
     */
    private static function legacyDelegationsFor(CommitteeRolesEnum $committeeRole): array
    {
        return match ($committeeRole) {
            CommitteeRolesEnum::PRESIDENT => [
                RoleEnum::CONTACTS, RoleEnum::SEASONS, RoleEnum::SUPERVISION, RoleEnum::FINES,
            ],
            CommitteeRolesEnum::VICE_PRESIDENT, CommitteeRolesEnum::SECRETARY => [
                RoleEnum::CONTACTS, RoleEnum::SEASONS, RoleEnum::SUPERVISION,
            ],
            CommitteeRolesEnum::TREASURER => [
                RoleEnum::TREASURY, RoleEnum::CASH_REGISTER, RoleEnum::FINES, RoleEnum::SUPERVISION,
            ],
            CommitteeRolesEnum::ADMINISTRATOR => [],
        };
    }
};
