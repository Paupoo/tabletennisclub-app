<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How often a player is expected to win, by ranking gap.
 *
 * A fixed and readable table rather than an Elo of our own (decided
 * 2026-10-25): a second ranking beside the federation's would be impossible to
 * defend in front of a parent, while "one cran above, we expect 38 %" can be
 * argued over in a committee meeting — and changed from the site.
 *
 * `gap` is counted from our player's side: positive when the opponent is
 * stronger. The extremes stand for everything beyond them.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('expected_win_rates');
    }

    public function up(): void
    {
        Schema::create('expected_win_rates', function (Blueprint $table): void {
            $table->id();
            $table->tinyInteger('gap')->unique();
            $table->unsignedTinyInteger('rate');
            $table->timestamps();
        });

        $now = now();

        DB::table('expected_win_rates')->insert(array_map(
            static fn (int $gap, int $rate): array => ['gap' => $gap, 'rate' => $rate, 'created_at' => $now, 'updated_at' => $now],
            [-4, -3, -2, -1, 0, 1, 2, 3, 4],
            [90, 82, 73, 62, 50, 38, 27, 18, 10],
        ));
    }
};
