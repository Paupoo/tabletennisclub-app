<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The matches our members played in official tournaments, one row each.
 *
 * Its own table, never a flag on `interclub_individual_matches`: every
 * interclub reader — a member's record, a tie, what captains decide on — reads
 * that table without a filter, and a tournament line that slipped in would
 * skew all of them without anyone noticing. Kept apart, a tournament can only
 * be counted where someone wrote the union on purpose.
 *
 * Named "official" because `tournaments` and `tournament_matches` already are
 * the club's own tournaments.
 *
 * No unique key: the federation gives these lines no identifier at all, and
 * two players can meet twice the same day in the same serie — once in a pool,
 * once in the draw — with the same score. The import replaces a player's
 * season wholesale instead, which is why nothing may ever point at a row.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('official_tournament_matches');
    }

    public function up(): void
    {
        Schema::create('official_tournament_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Season::class)->constrained()->cascadeOnDelete();

            // The licence is what the import replaces by and always kept; the
            // member is resolved from it and null while the roster does not
            // know the licence yet.
            $table->string('player_licence', 16);
            $table->string('player_name');
            $table->string('player_ranking', 8)->nullable();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();

            $table->date('played_on');
            $table->string('tournament_name');
            $table->string('serie_name')->nullable();

            $table->string('opponent_licence', 16)->nullable();
            $table->string('opponent_name');
            $table->string('opponent_ranking', 8)->nullable();
            $table->string('opponent_club')->nullable();

            $table->unsignedTinyInteger('our_sets')->nullable();
            $table->unsignedTinyInteger('their_sets')->nullable();
            $table->boolean('we_won');

            $table->timestamps();

            $table->index(['season_id', 'player_licence']);
            $table->index(['user_id', 'played_on']);
        });
    }
};
