<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the four boolean flags. Every decision now goes through a permission.
 *
 * The data moved to the roles tables in the earlier backfill and has been kept in
 * step since, so nothing is lost — but down() rebuilds the columns from the roles
 * rather than leaving them empty, so a rollback lands on a working application
 * rather than one where nobody is an administrator.
 */
return new class extends Migration
{
    private const array FLAGS = [
        'is_admin' => Role::ADMINISTRATOR,
        'is_committee_member' => Role::COMMITTEE,
        'is_coach' => Role::COACH,
        'is_selector' => Role::SELECTIONS,
    ];

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (array_keys(self::FLAGS) as $flag) {
                $table->boolean($flag)->default(false);
            }
        });

        foreach (self::FLAGS as $flag => $role) {
            $ids = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', $role->value)
                ->where('model_has_roles.model_type', User::class)
                ->pluck('model_has_roles.model_id');

            if ($ids->isNotEmpty()) {
                DB::table('users')->whereIn('id', $ids)->update([$flag => true]);
            }
        }
    }

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::FLAGS));
        });
    }
};
