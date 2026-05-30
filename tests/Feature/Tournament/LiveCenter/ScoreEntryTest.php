<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Pool;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Services\TournamentTableService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

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

// ── Table score entry Livewire component ──────────────────────────────────────

describe('TableScoreEntry Livewire component', function () {

    function scoreEntryComponent(User $user, Tournament $tournament, TournamentMatch $match, Table $table)
    {
        return Livewire::actingAs($user)
            ->test('pages::club-events.tournaments.table-score-entry', [
                'tournament' => $tournament,
                'table' => $table,
                'match' => $match,
            ]);
    }

    it('redirects to the same page after a valid score submission', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament(['sets_to_win' => 3]);
        $match = makePoolMatch($tournament, $p1, $p2);

        $room = Room::factory()->create();
        $table = Table::create([
            'name' => 'T1', 'state' => 'used',
            'purchased_on' => now()->subYears(1)->toDateString(),
            'room_id' => $room->id,
        ]);
        $tournament->tables()->attach($table->id, [
            'is_table_free' => false,
            'tournament_match_id' => $match->id,
            'match_started_at' => now()->subMinutes(5),
        ]);

        scoreEntryComponent($p1, $tournament, $match, $table)
            ->set('setScores.0.p1', '11')
            ->set('setScores.0.p2', '5')
            ->set('setScores.1.p1', '11')
            ->set('setScores.1.p2', '7')
            ->set('setScores.2.p1', '11')
            ->set('setScores.2.p2', '4')
            ->call('submitScore')
            ->assertRedirect(route('tournament.table.score', [
                'tournament' => $tournament->id,
                'table' => $table->id,
            ]));
    })->group('score', 'livewire');

    it('does not set submitted when no sets are entered', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament();
        $match = makePoolMatch($tournament, $p1, $p2);

        $room = Room::factory()->create();
        $table = Table::create([
            'name' => 'T2', 'state' => 'used',
            'purchased_on' => now()->subYears(1)->toDateString(),
            'room_id' => $room->id,
        ]);
        $tournament->tables()->attach($table->id, [
            'is_table_free' => false,
            'tournament_match_id' => $match->id,
            'match_started_at' => now()->subMinutes(5),
        ]);

        scoreEntryComponent($p1, $tournament, $match, $table)
            ->call('submitScore')
            ->assertSet('submitted', false);
    })->group('score', 'livewire');

    it('does not set submitted when match is not yet finished', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament(['sets_to_win' => 3]);
        $match = makePoolMatch($tournament, $p1, $p2);

        $room = Room::factory()->create();
        $table = Table::create([
            'name' => 'T3', 'state' => 'used',
            'purchased_on' => now()->subYears(1)->toDateString(),
            'room_id' => $room->id,
        ]);
        $tournament->tables()->attach($table->id, [
            'is_table_free' => false,
            'tournament_match_id' => $match->id,
            'match_started_at' => now()->subMinutes(5),
        ]);

        // Only one set entered — not enough for a winner
        scoreEntryComponent($p1, $tournament, $match, $table)
            ->set('setScores.0.p1', '11')
            ->set('setScores.0.p2', '5')
            ->call('submitScore')
            ->assertSet('submitted', false);
    })->group('score', 'livewire');

    it('marks the match as completed after submission', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament(['sets_to_win' => 3]);
        $match = makePoolMatch($tournament, $p1, $p2);

        $room = Room::factory()->create();
        $table = Table::create([
            'name' => 'T4', 'state' => 'used',
            'purchased_on' => now()->subYears(1)->toDateString(),
            'room_id' => $room->id,
        ]);
        $tournament->tables()->attach($table->id, [
            'is_table_free' => false,
            'tournament_match_id' => $match->id,
            'match_started_at' => now()->subMinutes(5),
        ]);

        scoreEntryComponent($p1, $tournament, $match, $table)
            ->set('setScores.0.p1', '11')->set('setScores.0.p2', '5')
            ->set('setScores.1.p1', '11')->set('setScores.1.p2', '7')
            ->set('setScores.2.p1', '11')->set('setScores.2.p2', '4')
            ->call('submitScore');

        expect($match->fresh()->status)->toBe('completed');
    })->group('score', 'livewire');

    it('frees the table after submission', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament(['sets_to_win' => 3]);
        $match = makePoolMatch($tournament, $p1, $p2);

        $room = Room::factory()->create();
        $table = Table::create([
            'name' => 'T5', 'state' => 'used',
            'purchased_on' => now()->subYears(1)->toDateString(),
            'room_id' => $room->id,
        ]);
        $tournament->tables()->attach($table->id, [
            'is_table_free' => false,
            'tournament_match_id' => $match->id,
            'match_started_at' => now()->subMinutes(5),
        ]);

        scoreEntryComponent($p1, $tournament, $match, $table)
            ->set('setScores.0.p1', '11')->set('setScores.0.p2', '5')
            ->set('setScores.1.p1', '11')->set('setScores.1.p2', '7')
            ->set('setScores.2.p1', '11')->set('setScores.2.p2', '4')
            ->call('submitScore');

        $pivot = DB::table('table_tournament')
            ->where('tournament_id', $tournament->id)
            ->where('table_id', $table->id)
            ->first();

        expect((bool) $pivot->is_table_free)->toBeTrue();
    })->group('score', 'livewire');

    // ── Live sync (rendering() hook) ─────────────────────────────────────────

    it('syncs setScores from DB on poll when referee has not typed anything', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament(['sets_to_win' => 3]);
        $match = makePoolMatch($tournament, $p1, $p2);

        $room = Room::factory()->create();
        $table = Table::create([
            'name' => 'T6', 'state' => 'used',
            'purchased_on' => now()->subYears(1)->toDateString(),
            'room_id' => $room->id,
        ]);
        $tournament->tables()->attach($table->id, [
            'is_table_free' => false,
            'tournament_match_id' => $match->id,
            'match_started_at' => now()->subMinutes(5),
        ]);

        $component = scoreEntryComponent($p1, $tournament, $match, $table);

        // Simulate admin entering a set from the live-center (writes directly to DB)
        $match->saveDraft([['player1_score' => 11, 'player2_score' => 5]]);

        // Simulate a poll: calling render() re-invokes rendering() which syncs from DB
        $component->call('$refresh');

        expect($component->get('setScores.0.p1'))->toBe('11')
            ->and($component->get('setScores.0.p2'))->toBe('5');
    })->group('score', 'livewire', 'sync');

    it('does not overwrite setScores from DB once the referee has typed', function () {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $tournament = makePendingTournament(['sets_to_win' => 3]);
        $match = makePoolMatch($tournament, $p1, $p2);

        $room = Room::factory()->create();
        $table = Table::create([
            'name' => 'T7', 'state' => 'used',
            'purchased_on' => now()->subYears(1)->toDateString(),
            'room_id' => $room->id,
        ]);
        $tournament->tables()->attach($table->id, [
            'is_table_free' => false,
            'tournament_match_id' => $match->id,
            'match_started_at' => now()->subMinutes(5),
        ]);

        $component = scoreEntryComponent($p1, $tournament, $match, $table);

        // Referee types their own score — sets scoresDirty = true
        $component->set('setScores.0.p1', '9');

        // Admin enters a different score directly in DB
        $match->saveDraft([['player1_score' => 11, 'player2_score' => 5]]);

        // Poll fires — should NOT overwrite the referee's "9"
        $component->call('$refresh');

        expect($component->get('setScores.0.p1'))->toBe('9');
    })->group('score', 'livewire', 'sync');

})->group('score');
