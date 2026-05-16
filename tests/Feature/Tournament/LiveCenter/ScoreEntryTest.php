<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Pool;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Services\TournamentTableService;
use Illuminate\Support\Facades\DB;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makePendingTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PENDING,
        'sets_to_win' => 3,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ], $overrides));
}

function makePoolMatch(Tournament $tournament, User $p1, User $p2): TournamentMatch
{
    $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
    $pool->users()->attach([$p1->id, $p2->id]);

    return TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => $pool->id,
        'player1_id' => $p1->id,
        'player2_id' => $p2->id,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => 'scheduled',
        'match_order' => 1,
    ]);
}

// ── TournamentMatch::recordResult ─────────────────────────────────────────────

describe('TournamentMatch::recordResult', function () {

    it('creates the correct number of MatchSet records', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 11, 'player2_score' => 5],
            ['player1_score' => 11, 'player2_score' => 9],
        ]);

        expect($match->fresh()->sets)->toHaveCount(3);
    })->group('score', 'match');

    it('assigns the winner of each set correctly', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 5],  // p1 wins
            ['player1_score' => 6,  'player2_score' => 11], // p2 wins
            ['player1_score' => 11, 'player2_score' => 8],  // p1 wins
        ]);

        $sets = $match->fresh()->sets()->orderBy('set_number')->get();
        expect($sets[0]->winner_id)->toBe($p1->id)
            ->and($sets[1]->winner_id)->toBe($p2->id)
            ->and($sets[2]->winner_id)->toBe($p1->id);
    })->group('score', 'match');

    it('sets the match winner to the player who wins the most sets', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament(['sets_to_win' => 3]);
        $match = makePoolMatch($tournament, $p1, $p2);

        // p2 wins 3-2
        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 5],
            ['player1_score' => 5,  'player2_score' => 11],
            ['player1_score' => 11, 'player2_score' => 9],
            ['player1_score' => 8,  'player2_score' => 11],
            ['player1_score' => 7,  'player2_score' => 11],
        ]);

        expect($match->fresh()->winner_id)->toBe($p2->id);
    })->group('score', 'match');

    it('marks the match as completed', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 5],
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 11, 'player2_score' => 4],
        ]);

        expect($match->fresh()->status)->toBe('completed');
    })->group('score', 'match');

    it('replaces existing sets when re-recording a result', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 5],
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 11, 'player2_score' => 4],
        ]);

        // Re-record with different result
        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 8],
            ['player1_score' => 11, 'player2_score' => 9],
            ['player1_score' => 11, 'player2_score' => 6],
        ]);

        expect($match->fresh()->sets)->toHaveCount(3);
    })->group('score', 'match');

    it('getTotalPoints returns sum of scores for a player across all sets', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 9,  'player2_score' => 11],
            ['player1_score' => 11, 'player2_score' => 5],
        ]);

        $match->load('sets');
        expect($match->getTotalPoints($p1->id))->toBe(31)  // 11+9+11
            ->and($match->getTotalPoints($p2->id))->toBe(23); // 7+11+5
    })->group('score', 'match');

    it('getSetsWon counts won sets for a player', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 5,  'player2_score' => 11],
            ['player1_score' => 11, 'player2_score' => 9],
        ]);

        $match->load('sets');
        expect($match->getSetsWon($p1->id))->toBe(2)
            ->and($match->getSetsWon($p2->id))->toBe(1);
    })->group('score', 'match');

})->group('score');

// ── TournamentMatch::saveDraft ────────────────────────────────────────────────

describe('TournamentMatch::saveDraft', function () {

    it('creates set records but does not mark the match as completed', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $match->saveDraft([
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 5,  'player2_score' => 11],
        ]);

        expect($match->fresh()->status)->not->toBe('completed')
            ->and($match->fresh()->sets)->toHaveCount(2);
    })->group('score', 'draft');

    it('replaces previous draft sets on re-save', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $match->saveDraft([['player1_score' => 11, 'player2_score' => 7]]);
        $match->saveDraft([
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 11, 'player2_score' => 9],
        ]);

        expect($match->fresh()->sets)->toHaveCount(2);
    })->group('score', 'draft');

})->group('score');

// ── TournamentTableService::freeUsedTable ─────────────────────────────────────

describe('TournamentTableService::freeUsedTable', function () {

    it('marks the table as free and records match_ended_at after completing a match', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $room = Room::factory()->create();
        $table = Table::create([
            'name' => 'Table 1',
            'state' => 'used',
            'purchased_on' => now()->subYears(2)->toDateString(),
            'room_id' => $room->id,
        ]);
        $tournament->tables()->attach($table->id, [
            'is_table_free' => false,
            'tournament_match_id' => $match->id,
            'match_started_at' => now()->subMinutes(15),
        ]);

        $match->recordResult([
            ['player1_score' => 11, 'player2_score' => 5],
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 11, 'player2_score' => 4],
        ]);

        app(TournamentTableService::class)->freeUsedTable($match);

        $pivot = DB::table('table_tournament')
            ->where('tournament_id', $tournament->id)
            ->where('table_id', $table->id)
            ->first();

        expect((bool) $pivot->is_table_free)->toBeTrue()
            ->and($pivot->match_ended_at)->not->toBeNull();
    })->group('score', 'table');

})->group('score');
