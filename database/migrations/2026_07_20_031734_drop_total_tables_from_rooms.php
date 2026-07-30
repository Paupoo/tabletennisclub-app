<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `rooms.total_tables` was written by TournamentTableService and by the setup
 * wizard, and read by nothing. It had already drifted: one room stored 5 while
 * holding 7 tables, because the counter is only refreshed when a tournament is
 * configured, never when a table is added or moved.
 *
 * The live count answers the same question and cannot drift. `withCount`
 * covers it at this scale.
 *
 * `total_playable_tables` stays: it has five readers in the tournament wizard.
 * The pivot `room_tournament.total_tables` is a different column and is
 * untouched.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->integer('total_tables')->default(0);
        });
    }

    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropColumn('total_tables');
        });
    }
};
