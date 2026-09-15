<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One roster row per player and per fixture.
 *
 * Three call sites write this pivot with a read-then-attach — markAvailability,
 * select, and the selection screen's save — and nothing in the schema held them
 * to it. Two requests arriving together both find no row and both attach, and
 * from then on the player is counted twice in every availability tally and
 * updated twice by every updateExistingPivot.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclub_user', function (Blueprint $table): void {
            $table->dropUnique('interclub_user_interclub_id_user_id_unique');
        });
    }

    public function up(): void
    {
        $this->removeDuplicateRosterRows();

        Schema::table('interclub_user', function (Blueprint $table): void {
            $table->unique(['interclub_id', 'user_id']);
        });
    }

    /**
     * Adding the index to a table that already holds duplicates fails, so the
     * duplicates go first — one pair at a time, through the query builder
     * rather than a dialect-specific delete, because the suite runs on SQLite
     * and the club on MySQL.
     *
     * The survivor is the most recently touched row: availability and selection
     * are both last-write-wins already, so it is the one the screens have been
     * showing.
     */
    private function removeDuplicateRosterRows(): void
    {
        $duplicates = DB::table('interclub_user')
            ->select('interclub_id', 'user_id')
            ->groupBy('interclub_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $survivor = DB::table('interclub_user')
                ->where('interclub_id', $duplicate->interclub_id)
                ->where('user_id', $duplicate->user_id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('interclub_user')
                ->where('interclub_id', $duplicate->interclub_id)
                ->where('user_id', $duplicate->user_id)
                ->where('id', '!=', $survivor)
                ->delete();
        }
    }
};
