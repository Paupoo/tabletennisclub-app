<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ranking our player carried on the evening, as the sheet prints it.
 *
 * `users.ranking` is only today's: a player who climbed from E2 to D6 would
 * see every old win measured against a ranking he did not hold yet. The sheet
 * names both sides on the same scale — the women's one in a women's tie — so
 * the gap read from these two columns is right without modelling either scale.
 *
 * Null on the double, and on every line imported before this column existed
 * until the history is imported again.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclub_individual_matches', function (Blueprint $table): void {
            $table->dropColumn('our_player_ranking');
        });
    }

    public function up(): void
    {
        Schema::table('interclub_individual_matches', function (Blueprint $table): void {
            $table->string('our_player_ranking', 8)->nullable()->after('our_player_licence');
        });
    }
};
