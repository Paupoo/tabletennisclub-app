<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->dropUnique('interclubs_season_aftt_match_unique');
            $table->dropColumn(['aftt_match_id', 'round_number']);
        });

        Schema::table('leagues', function (Blueprint $table): void {
            $table->dropColumn('aftt_division_id');
        });
    }

    /**
     * Gives a fixture and a division the federation's own name for themselves, so
     * an import run twice recognises what it wrote the first time.
     *
     * `aftt_match_id` rather than the federation's `MatchUniqueId`: that field
     * comes back empty on every row of every division the club plays in, so it
     * cannot be used. `MatchId` can, at one known cost — it encodes the round
     * (`PBBWH05/114`), so a division recomputed after a withdrawal reissues its
     * identifiers and the old rows become orphans rather than updates.
     *
     * `round_number` is the federation's own journée, kept beside `week_number`
     * and never in place of it. They are different things: `week_number` is the
     * ISO calendar week and carries the "already playing that week" rule across
     * categories, while a round is scoped to its category — men play eighteen
     * where veterans play seven, so round 5 is a different date in each. Storing
     * one in the other would silently break lineup conflict detection.
     */
    public function up(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->string('aftt_match_id', 20)->nullable()->after('id');
            $table->unsignedTinyInteger('round_number')->nullable()->after('week_number');

            $table->unique(['season_id', 'aftt_match_id'], 'interclubs_season_aftt_match_unique');
        });

        Schema::table('leagues', function (Blueprint $table): void {
            $table->unsignedInteger('aftt_division_id')->nullable()->after('division')->index();
        });
    }
};
