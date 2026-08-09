<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Support\Facades\Event;

function matchPool(array $tournamentOverrides = []): Pool
{
    $tournament = Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::SETUP,
        'match_type' => 'single',
        'has_handicap_points' => false,
        'duration_minutes' => 180,
        'pool_size' => 4,
        'nb_pools' => 2,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'logistics_buffer_minutes' => 3,
    ], $tournamentOverrides));

    Event::fake(); // prevent $dispatchesEvents listeners after create

    $pool = new Pool;
    $pool->name = 'Pool A';
    $pool->tournament_id = $tournament->id;
    $pool->save();

    return $pool;
}

// ── generateMatches ───────────────────────────────────────────────────────────

describe('generateMatches', function (): void {
    it('generates C(n,2) matches for n players (4 players → 6 matches)', function (): void {
        $pool = matchPool();
        $users = User::factory()->count(4)->create(['ranking' => 'NC']);
        foreach ($users as $user) {
            $pool->users()->attach($user->id);
        }

        $matches = (new TournamentMatchService)->generateMatches($pool->fresh());

        expect($matches->count())->toBe(6);
    });

    it('generates C(n,2) matches for 3 players (3 matches)', function (): void {
        $pool = matchPool();
        $users = User::factory()->count(3)->create(['ranking' => 'NC']);
        foreach ($users as $user) {
            $pool->users()->attach($user->id);
        }

        $matches = (new TournamentMatchService)->generateMatches($pool->fresh());

        expect($matches->count())->toBe(3);
    });

    it('persists matches to the database', function (): void {
        $pool = matchPool();
        $users = User::factory()->count(4)->create(['ranking' => 'NC']);
        foreach ($users as $user) {
            $pool->users()->attach($user->id);
        }

        (new TournamentMatchService)->generateMatches($pool->fresh());

        $this->assertDatabaseCount('tournament_matches', 6);
    });

    it('assigns sequential match_order starting at 1', function (): void {
        $pool = matchPool();
        $users = User::factory()->count(4)->create(['ranking' => 'NC']);
        foreach ($users as $user) {
            $pool->users()->attach($user->id);
        }

        (new TournamentMatchService)->generateMatches($pool->fresh());

        $orders = TournamentMatch::where('pool_id', $pool->id)->pluck('match_order')->sort()->values();
        expect($orders->first())->toBe(1);
        expect($orders->last())->toBe(6);
    });

    it('regenerates matches by deleting old ones first', function (): void {
        $pool = matchPool();
        $users = User::factory()->count(4)->create(['ranking' => 'NC']);
        foreach ($users as $user) {
            $pool->users()->attach($user->id);
        }

        (new TournamentMatchService)->generateMatches($pool->fresh());
        (new TournamentMatchService)->generateMatches($pool->fresh());

        $this->assertDatabaseCount('tournament_matches', 6);
    });
});

// ── forfeitPoolMatchesForPlayer ───────────────────────────────────────────────

describe('forfeitPoolMatchesForPlayer', function (): void {
    it('marks all scheduled pool matches as completed with opponent as winner', function (): void {
        $pool = matchPool();
        $player = User::factory()->create(['ranking' => 'NC']);
        $opponent = User::factory()->create(['ranking' => 'NC']);
        $pool->users()->attach([$player->id, $opponent->id]);

        TournamentMatch::create([
            'pool_id' => $pool->id,
            'tournament_id' => $pool->tournament_id,
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        $count = (new TournamentMatchService)->forfeitPoolMatchesForPlayer(
            $pool->tournament,
            $player
        );

        expect($count)->toBe(1);

        $match = TournamentMatch::where('pool_id', $pool->id)->first();
        expect($match->status)->toBe('completed');
        expect($match->winner_id)->toBe($opponent->id);
        expect($match->is_forfeit)->toBeTrue();
    });

    it('returns 0 when player has no scheduled matches', function (): void {
        $pool = matchPool();
        $player = User::factory()->create(['ranking' => 'NC']);

        $count = (new TournamentMatchService)->forfeitPoolMatchesForPlayer(
            $pool->tournament,
            $player
        );

        expect($count)->toBe(0);
    });

    it('does not forfeit already completed matches', function (): void {
        $pool = matchPool();
        $player = User::factory()->create(['ranking' => 'NC']);
        $opponent = User::factory()->create(['ranking' => 'NC']);
        $pool->users()->attach([$player->id, $opponent->id]);

        TournamentMatch::create([
            'pool_id' => $pool->id,
            'tournament_id' => $pool->tournament_id,
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'completed',
            'winner_id' => $opponent->id,
            'match_order' => 1,
        ]);

        $count = (new TournamentMatchService)->forfeitPoolMatchesForPlayer(
            $pool->tournament,
            $player
        );

        expect($count)->toBe(0);
    });
});

// ── calculatePoolStandings ────────────────────────────────────────────────────

describe('calculatePoolStandings', function (): void {
    it('sorts players by matches_won descending', function (): void {
        $pool = matchPool();
        $winner = User::factory()->create(['ranking' => 'NC']);
        $loser = User::factory()->create(['ranking' => 'NC']);
        $pool->users()->attach([$winner->id, $loser->id]);

        TournamentMatch::create([
            'pool_id' => $pool->id,
            'tournament_id' => $pool->tournament_id,
            'player1_id' => $winner->id,
            'player2_id' => $loser->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'completed',
            'winner_id' => $winner->id,
            'match_order' => 1,
        ]);

        $standings = (new TournamentMatchService)->calculatePoolStandings($pool->fresh());

        expect($standings->first()['player']->id)->toBe($winner->id);
        expect($standings->last()['player']->id)->toBe($loser->id);
    });

    it('returns a collection with player stats', function (): void {
        $pool = matchPool();
        $user = User::factory()->create(['ranking' => 'NC']);
        $pool->users()->attach($user->id);

        $standings = (new TournamentMatchService)->calculatePoolStandings($pool->fresh());

        expect($standings->first())->toHaveKeys(['player', 'matches_played', 'matches_won', 'sets_won', 'total_points', 'no_show']);
    });

    it('puts no_show players at the bottom', function (): void {
        $pool = matchPool();
        $normal = User::factory()->create(['ranking' => 'NC']);
        $noShow = User::factory()->create(['ranking' => 'NC']);
        $pool->users()->attach([$normal->id, $noShow->id]);

        $tournament = $pool->tournament;
        $tournament->users()->attach($noShow->id, ['registration_status' => 'no_show']);

        $standings = (new TournamentMatchService)->calculatePoolStandings($pool->fresh());

        $lastItem = $standings->last();
        expect($lastItem['no_show'])->toBeTrue();
        expect($lastItem['player']->id)->toBe($noShow->id);
    });
});

// ── Handicap table ────────────────────────────────────────────────────────────

describe('AFTT handicap table', function (): void {
    /**
     * The table is transcribed federal data, not something derivable: the rungs
     * are unevenly spaced, so no formula over Ranking positions reproduces it.
     * What can be enforced is that it covers the enum exactly — a ranking added
     * to Ranking without a row and a column here would throw "Undefined array
     * key" in the middle of a draw.
     */
    it('covers every ranking in both dimensions', function (): void {
        $table = new ReflectionProperty(TournamentMatchService::class, 'handicapPoints')
            ->getValue(new TournamentMatchService);

        $rankings = array_column(Ranking::cases(), 'value');

        expect(array_keys($table))->toEqualCanonicalizing($rankings);

        foreach ($table as $row) {
            expect(array_keys($row))->toEqualCanonicalizing($rankings)
                ->and($row)->each->toBeInt();
        }
    });

    it('gives no points when both players hold the same ranking', function (string $ranking): void {
        $table = new ReflectionProperty(TournamentMatchService::class, 'handicapPoints')
            ->getValue(new TournamentMatchService);

        expect($table[$ranking][$ranking])->toBe(0);
    })->with(array_column(Ranking::cases(), 'value'));
});
