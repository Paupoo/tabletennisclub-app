<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

function conflictTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'single',
        'has_handicap_points' => false,
    ]);
}

function conflictPool(Tournament $tournament): Pool
{
    return Pool::factory()->for($tournament)->create(['name' => 'A']);
}

function conflictMatch(Tournament $tournament, int $p1, int $p2, ?int $referee = null, string $status = 'scheduled'): TournamentMatch
{
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => conflictPool($tournament)->id,
        'player1_id' => $p1,
        'player2_id' => $p2,
        'referee_id' => $referee,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => $status,
        'match_order' => 1,
    ]);

    if ($status === 'in_progress') {
        occupyConflictTable($match);
    }

    return $match;
}

function occupyConflictTable(TournamentMatch $match): void
{
    $table = Table::create([
        'name' => 'Table ' . $match->id,
        'state' => TableStateEnum::GOOD,
        'purchased_on' => now()->subYears(2)->toDateString(),
        'room_id' => Room::factory()->create()->id,
    ]);

    $match->tournament->tables()->attach($table->id, [
        'is_table_free' => false,
        'tournament_match_id' => $match->id,
        'match_started_at' => now()->subMinutes(15),
    ]);
}

describe('detectStartConflict', function (): void {
    it('returns null when no active matches exist', function (): void {
        $tournament = conflictTournament();
        [$a, $b] = User::factory(2)->create()->all();

        $match = conflictMatch($tournament, $a->id, $b->id);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $match))->toBeNull();
    });

    it('returns null when active players are all different', function (): void {
        $tournament = conflictTournament();
        [$a, $b, $c, $d] = User::factory(4)->create()->all();

        conflictMatch($tournament, $a->id, $b->id, status: 'in_progress');
        $next = conflictMatch($tournament, $c->id, $d->id);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->toBeNull();
    });

    it('detects a player already in an active match', function (): void {
        $tournament = conflictTournament();
        [$a, $b, $c] = User::factory(3)->create()->all();

        conflictMatch($tournament, $a->id, $b->id, status: 'in_progress');
        $next = conflictMatch($tournament, $b->id, $c->id); // $b still playing

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->not->toBeNull();
    });

    it('detects a referee already playing in an active match', function (): void {
        $tournament = conflictTournament();
        [$a, $b, $c, $d] = User::factory(4)->create()->all();

        // $a is playing in an active match
        conflictMatch($tournament, $a->id, $b->id, status: 'in_progress');
        // $a is also the referee of the next match
        $next = conflictMatch($tournament, $c->id, $d->id, referee: $a->id);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->not->toBeNull();
    });

    it('detects a referee already refereeing another active match', function (): void {
        $tournament = conflictTournament();
        [$a, $b, $c, $d, $ref] = User::factory(5)->create()->all();

        conflictMatch($tournament, $a->id, $b->id, referee: $ref->id, status: 'in_progress');
        $next = conflictMatch($tournament, $c->id, $d->id, referee: $ref->id);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->not->toBeNull();
    });

    // Real-world scenario: a doubles pair member is assigned as referee for another match
    // (exactly what happened in tournament 5: user 51 was in pair 8 playing at table 15
    //  AND assigned as referee for the match at table 2)
    it('detects a doubles pair member assigned as referee for another active match', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PENDING,
            'match_type' => 'double',
            'has_handicap_points' => false,
        ]);
        $admin = User::factory()->create();
        [$a, $b, $c, $d, $e, $f, $g, $h] = User::factory(8)->create()->all();

        $pair1 = TournamentPair::create(['tournament_id' => $tournament->id, 'player1_id' => $a->id, 'player2_id' => $b->id, 'registered_by' => $admin->id]);
        $pair2 = TournamentPair::create(['tournament_id' => $tournament->id, 'player1_id' => $c->id, 'player2_id' => $d->id, 'registered_by' => $admin->id]);
        $pair3 = TournamentPair::create(['tournament_id' => $tournament->id, 'player1_id' => $e->id, 'player2_id' => $f->id, 'registered_by' => $admin->id]);
        $pair4 = TournamentPair::create(['tournament_id' => $tournament->id, 'player1_id' => $g->id, 'player2_id' => $h->id, 'registered_by' => $admin->id]);

        $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);

        // Active match: pair1 vs pair2 — $b is playing
        $active = TournamentMatch::create([
            'tournament_id' => $tournament->id, 'pool_id' => $pool->id,
            'pair1_id' => $pair1->id, 'pair2_id' => $pair2->id,
            'player1_id' => $a->id, 'player2_id' => $c->id,
            'player1_handicap_points' => 0, 'player2_handicap_points' => 0,
            'status' => 'in_progress', 'match_order' => 1,
        ]);
        occupyConflictTable($active);

        // Next match: pair3 vs pair4, but $b (player2 of pair1) is the referee
        $next = TournamentMatch::create([
            'tournament_id' => $tournament->id, 'pool_id' => $pool->id,
            'pair1_id' => $pair3->id, 'pair2_id' => $pair4->id,
            'player1_id' => $e->id, 'player2_id' => $g->id,
            'referee_id' => $b->id,
            'player1_handicap_points' => 0, 'player2_handicap_points' => 0,
            'status' => 'scheduled', 'match_order' => 2,
        ]);
        $next->load(['pair1', 'pair2']);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->not->toBeNull();
    });
})->group('Tournament', 'Conflict');

// ── La file, calculée une seule fois ─────────────────────────────────────────

/*
 * Le drapeau « joueur en piste » vivait en double, en Blade : une fois dans
 * l'onglet À venir, une fois dans le tiroir de lancement, avec le même
 * intersect recopié. Deux vues d'une seule file, à tenir synchronisées à la
 * main. Il est maintenant calculé par queue() et les deux vues le lisent.
 */
describe('queue', function (): void {

    it('flags the side whose player is already on a table', function (): void {
        $tournament = conflictTournament();
        [$busy, $free, $other] = User::factory(3)->create()->all();

        conflictMatch($tournament, $busy->id, $other->id, status: 'in_progress');
        $waiting = conflictMatch($tournament, $free->id, $busy->id);

        $entry = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->get('queue')
            ->firstWhere(fn (array $row): bool => $row['match']->is($waiting));

        expect($entry['ready'])->toBeTrue()
            ->and($entry['blocked'])->toBeTrue()
            ->and($entry['side1Blocked'])->toBeFalse()
            ->and($entry['side2Blocked'])->toBeTrue();
    });

    it('leaves a match alone when both players are available', function (): void {
        $tournament = conflictTournament();
        [$a, $b] = User::factory(2)->create()->all();

        $waiting = conflictMatch($tournament, $a->id, $b->id);

        $entry = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->get('queue')
            ->firstWhere(fn (array $row): bool => $row['match']->is($waiting));

        expect($entry['blocked'])->toBeFalse()->and($entry['ready'])->toBeTrue();
    });

    it('never calls a match with an undetermined player blocked', function (): void {
        $tournament = conflictTournament();
        $a = User::factory()->create();

        // Un match de tableau dont l'adversaire n'est pas encore connu.
        $pending = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'round' => 'final',
            'player1_id' => $a->id,
            'player2_id' => null,
            'status' => 'scheduled',
            'match_order' => 9,
        ]);

        $entry = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->get('queue')
            ->firstWhere(fn (array $row): bool => $row['match']->is($pending));

        expect($entry['ready'])->toBeFalse()->and($entry['blocked'])->toBeFalse();
    });

    /*
     * Le cas signalé en salle : le match recommandé était arbitré par une
     * joueuse déjà en piste sur une autre table. Rien ne le signalait, et le
     * lancer échouait sur « Conflit : … déjà dans un match en cours ».
     *
     * busyPlayerIds a toujours compté les arbitres des matchs en cours ; c'est
     * l'arbitre du match *entrant* que la file ne regardait pas.
     */
    it('flags a match whose referee is already on a table', function (): void {
        $tournament = conflictTournament();
        [$busy, $partner, $a, $b] = User::factory(4)->create()->all();

        conflictMatch($tournament, $busy->id, $partner->id, status: 'in_progress');
        $waiting = conflictMatch($tournament, $a->id, $b->id, referee: $busy->id);

        $entry = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->get('queue')
            ->firstWhere(fn (array $row): bool => $row['match']->is($waiting));

        expect($entry['ready'])->toBeTrue()
            ->and($entry['refereeBlocked'])->toBeTrue()
            ->and($entry['blocked'])->toBeTrue()
            // Les deux joueurs sont libres : c'est l'arbitre qu'il faut remplacer.
            ->and($entry['side1Blocked'])->toBeFalse()
            ->and($entry['side2Blocked'])->toBeFalse();
    });

    /*
     * L'invariant qui manquait. La file dit ce qui est jouable, le lancement le
     * vérifie — les deux doivent dire la même chose, sinon l'organisateur clique
     * sur un match recommandé et se prend un refus.
     */
    it('agrees with the launch check on every match it lists', function (): void {
        $tournament = conflictTournament();
        [$busy, $partner, $a, $b, $c, $d] = User::factory(6)->create()->all();

        conflictMatch($tournament, $busy->id, $partner->id, status: 'in_progress');
        conflictMatch($tournament, $a->id, $b->id, referee: $busy->id);   // arbitre pris
        conflictMatch($tournament, $busy->id, $c->id);                    // joueuse prise
        conflictMatch($tournament, $c->id, $d->id);                       // libre

        $queue = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->get('queue');

        $service = app(TournamentMatchService::class);

        expect($queue)->toHaveCount(3);

        foreach ($queue as $entry) {
            $refused = $service->detectStartConflict($tournament, $entry['match']) !== null;

            expect($entry['blocked'])->toBe(
                $refused,
                "queue said blocked={$entry['blocked']} for match {$entry['match']->id}, launch said refused=" . ($refused ? '1' : '0'),
            );
        }
    });
});

// ── Les identifiants d'un côté ───────────────────────────────────────────────

describe('TournamentMatch::sidePlayerIds', function (): void {

    it('returns the single player of each side', function (): void {
        $tournament = conflictTournament();
        [$a, $b] = User::factory(2)->create()->all();
        $match = conflictMatch($tournament, $a->id, $b->id);

        expect($match->sidePlayerIds(1)->all())->toBe([$a->id])
            ->and($match->sidePlayerIds(2)->all())->toBe([$b->id]);
    });

    it('returns both members of a pair', function (): void {
        $tournament = conflictTournament();
        [$a, $b, $c, $d] = User::factory(4)->create()->all();

        $pair = fn (int $p1, int $p2): TournamentPair => TournamentPair::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1,
            'player2_id' => $p2,
            'registered_by' => $p1,
        ]);

        $pair1 = $pair($a->id, $b->id);
        $pair2 = $pair($c->id, $d->id);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => conflictPool($tournament)->id,
            'pair1_id' => $pair1->id,
            'pair2_id' => $pair2->id,
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        expect($match->sidePlayerIds(1)->all())->toBe([$a->id, $b->id])
            ->and($match->sidePlayerIds(2)->all())->toBe([$c->id, $d->id]);
    });
});
