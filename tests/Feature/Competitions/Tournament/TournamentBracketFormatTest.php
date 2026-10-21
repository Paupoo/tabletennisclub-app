<?php

declare(strict_types=1);

use App\Data\Tournament\TournamentConfig;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Services\TournamentFinalPhaseService;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentSimulator;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

/*
 * The final phase does what the wizard announced: a bracket of the size set,
 * the top of each pool straight in, and the places left taken from the rank
 * just below, compared on averages so a pool of three is not a handicap.
 * Inside a pool, ties break the way the championship breaks them.
 */

function formatTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'single',
        'sets_to_win' => 2,
        'nb_pools' => 1,
        'nb_qualifiers_per_pool' => 2,
        'bracket_size' => 4,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
        'price' => 0,
    ], $overrides));
}

/**
 * A pool holding these players, its round robin generated.
 *
 * @param  list<string>  $names
 * @param  array<string, mixed>  $attributes
 */
function formatPool(Tournament $tournament, string $name, array $names, array $attributes = []): Pool
{
    $pool = Pool::factory()->for($tournament)->create(['name' => $name]);

    foreach ($names as $playerName) {
        $player = User::factory()->create(array_merge(['first_name' => $playerName], $attributes[$playerName] ?? []));
        $tournament->users()->attach($player->id, ['registration_status' => 'confirmed']);
        $pool->users()->attach($player->id);
    }

    app(TournamentMatchService::class)->generateMatches($pool->fresh());

    return $pool;
}

/**
 * Record the pool match between two players, sets given from the winner's side.
 *
 * @param  list<array{0: int, 1: int}>  $sets
 */
function decide(Pool $pool, string $winner, string $loser, array $sets = [[11, 5], [11, 5]]): void
{
    $match = TournamentMatch::where('pool_id', $pool->id)->with(['player1', 'player2'])->get()
        ->first(fn (TournamentMatch $m): bool => collect([$m->player1->first_name, $m->player2->first_name])->sort()->values()->all()
            === collect([$winner, $loser])->sort()->values()->all());

    $winnerIsPlayer1 = $match->player1->first_name === $winner;
    $match->recordResult(array_map(fn (array $set): array => $winnerIsPlayer1
        ? ['player1_score' => $set[0], 'player2_score' => $set[1]]
        : ['player1_score' => $set[1], 'player2_score' => $set[0]], $sets));
}

/** @return list<string> */
function standingNames(Pool $pool): array
{
    return app(TournamentMatchService::class)->calculatePoolStandings($pool->fresh())
        ->map(fn (array $row): string => $row['player']->first_name)->values()->all();
}

/** @return list<string> */
function bracketNames(Tournament $tournament): array
{
    return TournamentMatch::where('tournament_id', $tournament->id)->whereNotNull('round')->with(['player1', 'player2'])->get()
        ->flatMap(fn (TournamentMatch $m): array => [$m->player1?->first_name, $m->player2?->first_name])
        ->filter()->sort()->values()->all();
}

describe('breaking ties inside a pool', function (): void {
    it('settles two players level on wins by their own match, whatever the sets elsewhere', function (): void {
        $pool = formatPool(formatTournament(), 'Pool A', ['Eve', 'Fay', 'Gus', 'Hal']);
        decide($pool, 'Fay', 'Eve', [[11, 9], [5, 11], [11, 9]]);
        decide($pool, 'Eve', 'Gus', [[11, 0], [11, 0]]);
        decide($pool, 'Eve', 'Hal', [[11, 0], [11, 0]]);
        decide($pool, 'Gus', 'Fay', [[11, 9], [5, 11], [11, 9]]);
        decide($pool, 'Fay', 'Hal', [[11, 9], [5, 11], [11, 9]]);
        decide($pool, 'Hal', 'Gus');

        // Eve and Fay hold two wins, Eve with far more points; Fay won their match.
        // Gus and Hal hold one: Hal won theirs.
        expect(standingNames($pool))->toBe(['Fay', 'Eve', 'Hal', 'Gus']);
    });

    it('orders a triangle by the sets won between the three of them', function (): void {
        $pool = formatPool(formatTournament(), 'Pool A', ['Ann', 'Bob', 'Cid', 'Dan']);
        decide($pool, 'Ann', 'Bob', [[11, 0], [11, 0]]);
        decide($pool, 'Bob', 'Cid', [[11, 9], [5, 11], [11, 9]]);
        decide($pool, 'Cid', 'Ann', [[11, 9], [5, 11], [11, 9]]);
        decide($pool, 'Ann', 'Dan');
        decide($pool, 'Bob', 'Dan');
        decide($pool, 'Cid', 'Dan');

        // Between the three: Ann won 3 sets of 5, Cid 3 of 6, Bob 2 of 5.
        expect(standingNames($pool))->toBe(['Ann', 'Cid', 'Bob', 'Dan']);
    });
});

describe('who goes to the bracket', function (): void {
    it('fills the bracket with the best of the rank below, compared on averages', function (): void {
        $tournament = formatTournament(['nb_pools' => 3, 'nb_qualifiers_per_pool' => 2, 'bracket_size' => 8]);

        $a = formatPool($tournament, 'Pool A', ['A1', 'A2', 'A3', 'A4']);
        decide($a, 'A1', 'A2');
        decide($a, 'A1', 'A3');
        decide($a, 'A1', 'A4');
        decide($a, 'A2', 'A3');
        decide($a, 'A2', 'A4');
        decide($a, 'A3', 'A4');

        $b = formatPool($tournament, 'Pool B', ['B1', 'B2', 'B3', 'B4']);
        decide($b, 'B1', 'B2');
        decide($b, 'B1', 'B3');
        decide($b, 'B1', 'B4');
        decide($b, 'B2', 'B3');
        decide($b, 'B2', 'B4');
        decide($b, 'B3', 'B4', [[11, 9], [5, 11], [11, 9]]);

        $c = formatPool($tournament, 'Pool C', ['C1', 'C2', 'C3']);
        decide($c, 'C1', 'C2');
        decide($c, 'C1', 'C3');
        decide($c, 'C2', 'C3');

        app(TournamentFinalPhaseService::class)->configureKnockoutPhase($tournament->fresh(), 'round_8');

        // Two places for three thirds: A3 and B3 each won one of three, C3 none of two.
        expect(bracketNames($tournament))->toBe(['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2']);
    });

    it('takes a third of a pool of three over a third of a pool of four with the same single win', function (): void {
        $tournament = formatTournament(['nb_pools' => 3, 'nb_qualifiers_per_pool' => 2, 'bracket_size' => 8]);

        $a = formatPool($tournament, 'Pool A', ['A1', 'A2', 'A3', 'A4']);
        decide($a, 'A1', 'A2');
        decide($a, 'A1', 'A3', [[11, 9], [5, 11], [11, 9]]);
        decide($a, 'A1', 'A4');
        decide($a, 'A2', 'A3', [[11, 9], [5, 11], [11, 9]]);
        decide($a, 'A2', 'A4');
        decide($a, 'A3', 'A4');

        // A triangle: every player wins one of two, and B1 comes out third.
        $b = formatPool($tournament, 'Pool B', ['B1', 'B2', 'B3']);
        decide($b, 'B3', 'B1');
        decide($b, 'B2', 'B3');
        decide($b, 'B1', 'B2', [[11, 9], [5, 11], [11, 9]]);

        $c = formatPool($tournament, 'Pool C', ['C1', 'C2', 'C3', 'C4']);
        decide($c, 'C1', 'C2');
        decide($c, 'C1', 'C3');
        decide($c, 'C1', 'C4');
        decide($c, 'C2', 'C3', [[11, 9], [5, 11], [11, 9]]);
        decide($c, 'C2', 'C4');
        decide($c, 'C3', 'C4', [[11, 9], [5, 11], [11, 9]]);

        app(TournamentFinalPhaseService::class)->configureKnockoutPhase($tournament->fresh(), 'round_8');

        // B1 won one of two; A3 and C3 one of three. A3 then lost fewer sets than C3.
        expect(bracketNames($tournament))->toBe(['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2']);
    });
});

describe('the bracket size', function (): void {
    it('is offered among the sizes the pools can fill', function (): void {
        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard')
            ->set('nb_poules', 3)
            ->set('nb_qualifies', 2)
            ->assertSet('bracketSize', 8)
            ->set('nb_poules', 4)
            ->assertSet('bracketSize', 8)
            ->set('nb_poules', 6)
            ->assertSet('bracketSize', 16);
    });

    it('refuses a size the pools cannot fill from the rank below', function (): void {
        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard')
            ->set('name', 'Format')
            ->set('nb_poules', 2)
            ->set('nb_qualifies', 1)
            ->set('bracketSize', 8)
            ->call('save')
            ->assertHasErrors(['bracketSize']);
    });

    it('is what the live centre builds', function (): void {
        $tournament = formatTournament(['nb_pools' => 3, 'nb_qualifiers_per_pool' => 1, 'bracket_size' => 4]);
        foreach (['A', 'B', 'C'] as $letter) {
            $pool = formatPool($tournament, "Pool {$letter}", ["{$letter}1", "{$letter}2", "{$letter}3"]);
            decide($pool, "{$letter}1", "{$letter}2");
            decide($pool, "{$letter}1", "{$letter}3");
            decide($pool, "{$letter}2", "{$letter}3");
        }

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->call('generateBracket');

        expect(TournamentMatch::where('tournament_id', $tournament->id)->where('round', 'semifinal')->count())->toBe(2)
            ->and(TournamentMatch::where('tournament_id', $tournament->id)->where('round', 'quarterfinal')->count())->toBe(0);
    });

    it('is never rebuilt over a bracket that already exists', function (): void {
        $tournament = formatTournament(['nb_pools' => 1, 'nb_qualifiers_per_pool' => 4, 'bracket_size' => 4]);
        $pool = formatPool($tournament, 'Pool A', ['Ann', 'Bob', 'Cid', 'Dan']);
        foreach ([['Ann', 'Bob'], ['Ann', 'Cid'], ['Ann', 'Dan'], ['Bob', 'Cid'], ['Bob', 'Dan'], ['Cid', 'Dan']] as [$w, $l]) {
            decide($pool, $w, $l);
        }
        $live = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->call('generateBracket');
        $semi = TournamentMatch::where('tournament_id', $tournament->id)->where('round', 'semifinal')->first();
        $semi->update(['status' => 'completed', 'winner_id' => $semi->player1_id]);

        $live->call('generateBracket');

        expect(TournamentMatch::find($semi->id)?->status)->toBe('completed');
    });
});

describe('handicap in the bracket', function (): void {
    it('gives the weaker side its starting points in a bracket match, as in the pools', function (): void {
        $tournament = formatTournament(['nb_pools' => 1, 'nb_qualifiers_per_pool' => 4, 'bracket_size' => 4, 'has_handicap_points' => true]);
        $pool = formatPool($tournament, 'Pool A', ['Ann', 'Bob', 'Cid', 'Dan'], [
            'Ann' => ['ranking' => 'B0'], 'Bob' => ['ranking' => 'C0'], 'Cid' => ['ranking' => 'D0'], 'Dan' => ['ranking' => 'E6'],
        ]);
        foreach ([['Ann', 'Bob'], ['Ann', 'Cid'], ['Ann', 'Dan'], ['Bob', 'Cid'], ['Bob', 'Dan'], ['Cid', 'Dan']] as [$w, $l]) {
            decide($pool, $w, $l, [[11, 9], [11, 9]]);
        }

        app(TournamentFinalPhaseService::class)->configureKnockoutPhase($tournament->fresh(), 'round_4');

        $semi = TournamentMatch::where('tournament_id', $tournament->id)->where('round', 'semifinal')->with(['player1', 'player2'])->get()
            ->first(fn (TournamentMatch $m): bool => in_array('Dan', [$m->player1->first_name, $m->player2->first_name], true));
        $danIsPlayer1 = $semi->player1->first_name === 'Dan';

        // Dan (E6) against Ann (B0): eight points to start, for Dan only.
        expect($danIsPlayer1 ? $semi->player1_handicap_points : $semi->player2_handicap_points)->toBe(8)
            ->and($danIsPlayer1 ? $semi->player2_handicap_points : $semi->player1_handicap_points)->toBe(0);
    });

    it('gives the starting points once a winner moves up to a match', function (): void {
        $tournament = formatTournament(['nb_pools' => 1, 'nb_qualifiers_per_pool' => 4, 'bracket_size' => 4, 'has_handicap_points' => true]);
        $pool = formatPool($tournament, 'Pool A', ['Ann', 'Bob', 'Cid', 'Dan'], [
            'Ann' => ['ranking' => 'B0'], 'Bob' => ['ranking' => 'C0'], 'Cid' => ['ranking' => 'D0'], 'Dan' => ['ranking' => 'E6'],
        ]);
        foreach ([['Ann', 'Bob'], ['Ann', 'Cid'], ['Ann', 'Dan'], ['Bob', 'Cid'], ['Bob', 'Dan'], ['Cid', 'Dan']] as [$w, $l]) {
            decide($pool, $w, $l, [[11, 9], [11, 9]]);
        }
        $service = app(TournamentFinalPhaseService::class);
        $service->configureKnockoutPhase($tournament->fresh(), 'round_4');

        foreach (TournamentMatch::where('tournament_id', $tournament->id)->where('round', 'semifinal')->with(['player1', 'player2'])->get() as $semi) {
            // The weaker player wins each semi: Cid and Dan reach the final.
            $winner = in_array($semi->player1->first_name, ['Cid', 'Dan'], true) ? $semi->player1_id : $semi->player2_id;
            $service->completeMatch($semi, $winner);
        }

        $final = TournamentMatch::where('tournament_id', $tournament->id)->where('round', 'final')->with(['player1', 'player2'])->first();
        $danIsPlayer1 = $final->player1->first_name === 'Dan';

        // Dan (E6) against Cid (D0): four points for Dan, from the federal table.
        expect($danIsPlayer1 ? $final->player1_handicap_points : $final->player2_handicap_points)->toBe(4);
    });
});

describe('the simulator', function (): void {
    it('counts the match for third place', function (): void {
        $result = app(TournamentSimulator::class)->simulate(new TournamentConfig(
            durationMinutes: 240, nbTables: 8, logisticsBufferMinutes: 3, poolSize: 4, nbPools: 2,
            nbQualifiersPerPool: 2, setsToWin: 3, matchType: 'double',
        ));

        // Two semis, a final and the bronze match.
        expect($result->bracketMatchesTotal)->toBe(4);
    });

    it('plays the bracket one round after the other', function (): void {
        $result = app(TournamentSimulator::class)->simulate(new TournamentConfig(
            durationMinutes: 240, nbTables: 8, logisticsBufferMinutes: 3, poolSize: 4, nbPools: 4,
            nbQualifiersPerPool: 2, setsToWin: 3, matchType: 'double',
        ));

        // 27 min a match. Pools: 4 pools × 3 rounds, two tables each = 3 rounds.
        // Bracket: quarters, semis, then final and bronze side by side = 3 rounds.
        expect($result->estimatedMinutes)->toBe(6 * 27);
    });

    it('plans the bracket of the size set, repêchés included', function (): void {
        $result = app(TournamentSimulator::class)->simulate(new TournamentConfig(
            durationMinutes: 240, nbTables: 8, logisticsBufferMinutes: 3, poolSize: 4, nbPools: 4,
            nbQualifiersPerPool: 1, setsToWin: 3, matchType: 'single', bracketSize: 8,
        ));

        // 4 straight in, 4 from the rank below: quarters, semis, final and bronze.
        expect($result->finalistsCount)->toBe(8)
            ->and($result->bracketMatchesTotal)->toBe(8);
    });

    it('plans the smallest bracket the pools can fill when no size is set', function (int $nbPools, int $nbQualifiers, int $expected): void {
        $simulator = app(TournamentSimulator::class);

        $result = $simulator->simulate(new TournamentConfig(
            durationMinutes: 240, nbTables: 8, logisticsBufferMinutes: 3, poolSize: 4, nbPools: $nbPools,
            nbQualifiersPerPool: $nbQualifiers, setsToWin: 3, matchType: 'single',
        ));

        expect($result->finalistsCount)->toBe($expected)
            ->and($simulator->fittingBracketSize($nbPools, $nbQualifiers))->toBe($expected);
    })->with([
        '3 pools × 2: 6 straight in, 2 repêchés' => [3, 2, 8],
        '4 pools × 2: 8 straight in' => [4, 2, 8],
        '6 pools × 2: 12 straight in, 4 repêchés' => [6, 2, 16],
    ]);

    it('counts the matches each entrant plays, two to a match', function (): void {
        $result = app(TournamentSimulator::class)->simulate(new TournamentConfig(
            durationMinutes: 240, nbTables: 8, logisticsBufferMinutes: 3, poolSize: 6, nbPools: 4,
            nbQualifiersPerPool: 2, setsToWin: 3, matchType: 'double', bracketSize: 8,
        ));

        // 60 pool matches + 8 in the bracket, two entrants each, over 24 pairs.
        expect($result->avgMatchesPerPlayer)->toBe(5.7);
    });
});

describe('the simulation in the wizard', function (): void {
    it('follows the bracket size chosen', function (): void {
        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard')
            ->set('nb_poules', 4)
            ->set('nb_qualifies', 1)
            ->set('bracketSize', 4)
            ->assertSeeText('tableau de 4')
            ->set('bracketSize', 8)
            ->assertSeeText('tableau de 8');
    });

    it('counts pairs and players apart in a doubles tournament', function (): void {
        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard')
            ->set('matchType', 'double')
            ->set('nb_poules', 4)
            ->set('pool_size', 6)
            ->set('nb_qualifies', 2)
            ->assertSeeText('24 paires · 48 joueurs')
            ->assertSeeText('4 poules de 6 → tableau de 8')
            ->assertSeeText('Matchs par paire');
    });

    it('counts players in a singles tournament', function (): void {
        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard')
            ->set('matchType', 'single')
            ->set('nb_poules', 4)
            ->set('pool_size', 6)
            ->set('nb_qualifies', 2)
            ->assertSeeText('24 joueurs')
            ->assertDontSeeText('24 paires')
            ->assertSeeText('Matchs par joueur');
    });
});
