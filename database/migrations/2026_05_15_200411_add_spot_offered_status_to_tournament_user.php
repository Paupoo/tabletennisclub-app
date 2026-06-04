<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                UPDATE tournament_user SET registration_status = 'cancelled'
                WHERE registration_status = 'spot_offered'
            ");
            DB::statement("
                ALTER TABLE tournament_user
                MODIFY COLUMN registration_status
                ENUM('registered','confirmed','no_show','cancelled','waiting')
                NOT NULL DEFAULT 'registered'
            ");
        }
    }

    /**
     * Add 'spot_offered' to the registration_status allowed values.
     *
     * spot_offered: user was promoted from the waitlist and has 48 h to confirm.
     * Their spot is reserved (counts toward activeRegistrationsCount).
     * Transitions: spot_offered → registered (confirmed) | cancelled (declined / expired)
     *
     * SQLite stores registration_status as VARCHAR, so no DDL change is needed.
     * For MySQL/PostgreSQL environments this migration documents the intent and
     * should be accompanied by the appropriate MODIFY COLUMN statement.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE tournament_user
                MODIFY COLUMN registration_status
                ENUM('registered','confirmed','no_show','cancelled','waiting','spot_offered')
                NOT NULL DEFAULT 'registered'
            ");
        }
    }
};
