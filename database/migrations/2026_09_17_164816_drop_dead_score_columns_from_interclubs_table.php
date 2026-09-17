<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\InterclubResultEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One home for a score, and it is `interclub_results`.
 *
 * `interclubs.score` and `interclubs.result` were never written by anything —
 * not the results screen, not the seeder, not the federation importer — while
 * `interclub_results` carried every one of them. Two screens nevertheless read
 * the empty pair and reported what they found: the team sheet showed "—" on
 * matches that had a score, and the dashboard's "last result" block queried
 * `whereNotNull('result')`, matched nothing, and therefore never rendered at
 * all. Both are repointed in this change; the columns go so the next reader
 * cannot make the same mistake.
 *
 * Dropped rather than backfilled: they hold no rows to save.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->string('score', 5)->nullable();
            $table->enum('result', array_column(InterclubResultEnum::cases(), 'value'))->nullable();
        });
    }

    public function up(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->dropColumn(['score', 'result']);
        });
    }
};
