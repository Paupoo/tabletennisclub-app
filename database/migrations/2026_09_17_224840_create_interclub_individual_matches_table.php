<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The federation's match sheets, one row per individual match.
 *
 * Everything is stored from our side of the tie, not the federation's home
 * side. The source writes every sheet home-first, so on an away evening a 3-1
 * win is filed as 1-3; normalising once here means no screen and no future
 * query ever has to remember to flip it.
 *
 * Flat rather than a match table plus a participants table. The richer shape was
 * meant to attribute the double, which is the one line a sheet never names:
 * checked over a full veterans division, all 27 ties carry an anonymous line at
 * the same position, with a set count and no player on either side. With
 * nothing to attribute, a join table would have bought generality nobody can
 * use — the double is kept as a row with no player, so the wins still add up to
 * the team score.
 *
 * `user_id` is null in three different situations, and they are not the same
 * thing: the double names nobody, the opponent is not one of our members, and
 * one of our own players carries a licence the club roster does not know. The
 * last is a data fault, so the name and the licence are kept next to the null
 * and the import reports it.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('interclub_individual_matches');
    }

    public function up(): void
    {
        Schema::create('interclub_individual_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Interclub::class)->constrained()->cascadeOnDelete();

            // Position within the tie, as the federation numbers it: 1-16 in
            // system 2, 1-10 in system 4 where 7 is the double.
            $table->unsignedTinyInteger('position');
            $table->boolean('is_double')->default(false);

            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('our_player_name')->nullable();
            $table->string('our_player_licence', 16)->nullable();

            $table->string('opponent_name')->nullable();
            $table->string('opponent_licence', 16)->nullable();
            $table->string('opponent_ranking', 8)->nullable();

            // Null on a forfeited line: the federation records no sets for a
            // match nobody turned up to play.
            $table->unsignedTinyInteger('our_sets')->nullable();
            $table->unsignedTinyInteger('their_sets')->nullable();
            $table->boolean('is_forfeit')->default(false);
            $table->boolean('we_won');

            $table->timestamps();

            // One line per position: re-importing a sheet corrects it in place
            // rather than stacking a second copy beside the first.
            $table->unique(['interclub_id', 'position']);
            $table->index('user_id');
        });
    }
};
