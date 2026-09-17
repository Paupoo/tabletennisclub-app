<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room for the identifiers the federation uses at the end of a season.
 *
 * Twenty characters covered every fixture of a regular division —
 * `PBBWH01/113` is eleven — and the column was sized on a season that had only
 * just started. Play-offs and champions rounds are named differently and
 * longer: `BAR.PBBWH.BAR-3.02/002` is twenty-two, and importing any past
 * season hits one within the first division it walks, rolling the whole import
 * back on a truncation.
 *
 * Sixty-four rather than twenty-four: the pattern is the federation's to
 * change, we have guessed it short once already, and the declared width of a
 * varchar costs nothing until it is used.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->string('aftt_match_id', 20)->nullable()->change();
        });
    }

    public function up(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->string('aftt_match_id', 64)->nullable()->change();
        });
    }
};
