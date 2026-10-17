<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Services\TournamentFinalPhaseService;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Mail\TournamentResultsMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * The live centre on the day: every mistake has a way back, a missing
 * referee or player never blocks a table, and the money can still be taken
 * at the desk once the tournament is under way.
 */

/**
 * A launched doubles tournament: one pool of three pairs, its matches drawn,
 * two tables in the hall.
 *
 * @return array{tournament: Tournament, pairs: list<TournamentPair>, tables: list<Table>}
 */
function liveDoubles(array $overrides = []): array
{
    $tournament = Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'double',
        'doubles_registration_mode' => 'club',
        'sets_to_win' => 2,
        'duration_minutes' => 180,
        'logistics_buffer_minutes' => 3,
        'pool_size' => 3,
        'max_users' => 0,
        'nb_pools' => 1,
        'nb_qualifiers_per_pool' => 2,
        'bracket_size' => 4,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
        'price' => 15,
    ], $overrides));

    $pool = Pool::factory()->for($tournament)->create(['name' => 'Pool A']);
    $pairs = [];

    foreach (['A', 'B', 'C'] as $letter) {
        $first = User::factory()->create(['first_name' => "{$letter}lpha", 'last_name' => "{$letter}one"]);
        $second = User::factory()->create(['first_name' => "{$letter}eta", 'last_name' => "{$letter}two"]);
        $tournament->users()->attach([$first->id, $second->id], ['registration_status' => 'confirmed']);
        $pair = $tournament->pairs()->create(['player1_id' => $first->id, 'player2_id' => $second->id, 'registered_by' => $first->id]);
        $pool->pairs()->attach($pair->id);
        $pairs[] = $pair;
    }

    app(TournamentMatchService::class)->generateMatches($pool->fresh());

    $room = Room::factory()->create(['name' => 'Salle du bas']);
    $tables = [];
    foreach ([1, 2] as $number) {
        $table = Table::factory()->create(['name' => (string) $number, 'room_id' => $room->id]);
        $tournament->tables()->attach($table->id, ['is_table_free' => true]);
        $tables[] = $table;
    }

    return ['tournament' => $tournament, 'pairs' => $pairs, 'tables' => $tables];
}

function liveCentre(Tournament $tournament): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);
}

function matchBetween(Tournament $tournament, TournamentPair $a, TournamentPair $b): TournamentMatch
{
    return TournamentMatch::where('tournament_id', $tournament->id)
        ->where(fn ($q) => $q->where(fn ($q) => $q->where('pair1_id', $a->id)->where('pair2_id', $b->id))
            ->orWhere(fn ($q) => $q->where('pair1_id', $b->id)->where('pair2_id', $a->id)))
        ->firstOrFail();
}

/**
 * Put a match on a table through the live centre.
 */
function launchOn(Testable $live, TournamentMatch $match, Table $table): Testable
{
    return $live->call('openLaunchDrawer', $table->id)->call('startMatch', $match->id);
}

/**
 * Enter a score through the live centre: side 1 wins two sets to love.
 */
function scoreSide1(Testable $live, TournamentMatch $match, ?Table $table = null): Testable
{
    return $live->call('openScoreEntry', $match->id, $table?->id)
        ->set('setScores.0', ['p1' => '11', 'p2' => '5'])
        ->set('setScores.1', ['p1' => '11', 'p2' => '5'])
        ->call('submitScore');
}

function tableIsFree(Tournament $tournament, Table $table): bool
{
    return (bool) $tournament->tables()->where('tables.id', $table->id)->first()?->pivot->is_table_free;
}

describe('the referee', function (): void {
    it('never stops a match from starting when the referee is busy on another table', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b], 'tables' => [$one, $two]] = liveDoubles();
        $poolB = Pool::factory()->for($t)->create(['name' => 'Pool B']);
        [$d, $e] = collect([1, 2])->map(function () use ($t, $poolB): TournamentPair {
            $first = User::factory()->create();
            $second = User::factory()->create();
            $t->users()->attach([$first->id, $second->id], ['registration_status' => 'confirmed']);
            $pair = $t->pairs()->create(['player1_id' => $first->id, 'player2_id' => $second->id, 'registered_by' => $first->id]);
            $poolB->pairs()->attach($pair->id);

            return $pair;
        })->all();
        app(TournamentMatchService::class)->generateMatches($poolB->fresh());
        $other = matchBetween($t, $d, $e);
        // Its referee is playing on table one.
        $other->update(['referee_id' => $a->player1_id]);

        $live = launchOn(liveCentre($t), matchBetween($t, $a, $b), $one);
        launchOn($live, $other, $two);

        expect($other->fresh()->status)->toBe('in_progress');
    });

    it('still refuses a match whose player is on another table', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b, $c], 'tables' => [$one, $two]] = liveDoubles();

        $live = launchOn(liveCentre($t), matchBetween($t, $a, $b), $one);
        launchOn($live, matchBetween($t, $a, $c), $two);

        expect(matchBetween($t, $a, $c)->status)->toBe('scheduled');
    });

    it('can be replaced by any registered player, or left out', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b, $c]] = liveDoubles();
        $match = matchBetween($t, $a, $b);

        $live = liveCentre($t)->call('changeReferee', $match->id, $c->player2_id);
        expect($match->fresh()->referee_id)->toBe($c->player2_id);

        $live->call('changeReferee', $match->id, null);
        expect($match->fresh()->referee_id)->toBeNull();
    });

    it('cannot be one of the players of the match', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b]] = liveDoubles();
        $match = matchBetween($t, $a, $b);
        $match->update(['referee_id' => null]);

        liveCentre($t)->call('changeReferee', $match->id, $a->player2_id);

        expect($match->fresh()->referee_id)->toBeNull();
    });
});

describe('the tables of the day', function (): void {
    it('are the ones ticked at the launch, broken ones left out by default', function (): void {
        $room = Room::factory()->create();
        $kept = Table::factory()->create(['name' => '1', 'room_id' => $room->id]);
        $dropped = Table::factory()->create(['name' => '2', 'room_id' => $room->id]);
        $broken = Table::factory()->create(['name' => '3', 'room_id' => $room->id, 'state' => TableStateEnum::OUT_OF_SERVICE]);
        ['tournament' => $t] = liveDoubles(['status' => TournamentStatusEnum::SETUP]);
        $t->tables()->detach();
        $t->rooms()->sync([$room->id]);

        $wizard = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $t])
            ->set('step', '5');
        expect($wizard->get('launchTableIds'))->toEqualCanonicalizing([$kept->id, $dropped->id]);

        $wizard->set('launchTableIds', [$kept->id])->call('processLaunch');

        expect($t->tables()->pluck('tables.id')->all())->toBe([$kept->id])
            ->and($broken->id)->not->toBeIn($t->tables()->pluck('tables.id')->all());
    });

    it('can grow and shrink during the day, but never lose a table in play', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b], 'tables' => [$one]] = liveDoubles();
        $upstairs = Table::factory()->create(['name' => '9', 'room_id' => Room::factory()->create(['name' => 'Salle du haut'])->id]);

        $live = launchOn(liveCentre($t), matchBetween($t, $a, $b), $one)
            ->call('addTable', $upstairs->id)
            ->call('removeTable', $one->id);

        expect($t->tables()->pluck('tables.id')->all())->toContain($upstairs->id)
            ->toContain($one->id);

        $live->call('removeTable', $upstairs->id);
        expect($t->tables()->pluck('tables.id')->all())->not->toContain($upstairs->id);
    });
});

describe('a way back', function (): void {
    it('cancels a match launched by mistake and frees its table', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b], 'tables' => [$one]] = liveDoubles();
        $match = matchBetween($t, $a, $b);

        launchOn(liveCentre($t), $match, $one)->call('cancelLaunch', $match->id);

        expect($match->fresh()->status)->toBe('scheduled')
            ->and(tableIsFree($t, $one))->toBeTrue();
    });

    it('corrects a pool score while the bracket is not drawn', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b], 'tables' => [$one]] = liveDoubles();
        $match = matchBetween($t, $a, $b);
        $live = scoreSide1(launchOn(liveCentre($t), $match, $one), $match, $one);
        $winnerBefore = $match->fresh()->winner_id;

        $live->call('openScoreEntry', $match->id)
            ->set('setScores.0', ['p1' => '5', 'p2' => '11'])
            ->set('setScores.1', ['p1' => '5', 'p2' => '11'])
            ->call('submitScore');

        expect($match->fresh()->winner_id)->not->toBe($winnerBefore)
            ->and($match->fresh()->winner_id)->toBe($match->player2_id);
    });

    it('refuses to correct a pool score once the bracket exists', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b]] = liveDoubles();
        $match = matchBetween($t, $a, $b);
        $match->recordResult([['player1_score' => 11, 'player2_score' => 5], ['player1_score' => 11, 'player2_score' => 5]]);
        TournamentMatch::create(['tournament_id' => $t->id, 'round' => 'final', 'match_order' => 1, 'status' => 'scheduled']);

        liveCentre($t)->call('openScoreEntry', $match->id)
            ->set('setScores.0', ['p1' => '5', 'p2' => '11'])
            ->set('setScores.1', ['p1' => '5', 'p2' => '11'])
            ->call('submitScore');

        expect($match->fresh()->winner_id)->toBe($match->player1_id);
    });

    it('corrects a semi-final: the new winner and the new loser take the right places', function (): void {
        ['tournament' => $t] = liveDoubles();
        [$semiA, $semiB] = semiFinalsOf($t);
        $service = app(TournamentFinalPhaseService::class);
        $semiA->recordResult([['player1_score' => 11, 'player2_score' => 5], ['player1_score' => 11, 'player2_score' => 5]]);
        $service->completeMatch($semiA->fresh(), $semiA->player1_id);
        $semiB->recordResult([['player1_score' => 11, 'player2_score' => 5], ['player1_score' => 11, 'player2_score' => 5]]);
        $service->completeMatch($semiB->fresh(), $semiB->player1_id);

        liveCentre($t)->call('openScoreEntry', $semiA->id)
            ->set('setScores.0', ['p1' => '5', 'p2' => '11'])
            ->set('setScores.1', ['p1' => '5', 'p2' => '11'])
            ->call('submitScore');

        $final = TournamentMatch::where('tournament_id', $t->id)->where('round', 'final')->first();
        $bronze = TournamentMatch::where('tournament_id', $t->id)->where('round', 'bronze')->first();
        expect([$final->player1_id, $final->player2_id])->toEqualCanonicalizing([$semiA->player2_id, $semiB->player1_id])
            ->and([$bronze->player1_id, $bronze->player2_id])->toEqualCanonicalizing([$semiA->player1_id, $semiB->player2_id]);
    });

    it('refuses to correct a semi-final once the final is under way', function (): void {
        ['tournament' => $t] = liveDoubles();
        [$semiA, $semiB] = semiFinalsOf($t);
        $service = app(TournamentFinalPhaseService::class);
        foreach ([$semiA, $semiB] as $semi) {
            $semi->recordResult([['player1_score' => 11, 'player2_score' => 5], ['player1_score' => 11, 'player2_score' => 5]]);
            $service->completeMatch($semi->fresh(), $semi->player1_id);
        }
        TournamentMatch::where('tournament_id', $t->id)->where('round', 'final')->update(['status' => 'in_progress']);

        liveCentre($t)->call('openScoreEntry', $semiA->id)
            ->set('setScores.0', ['p1' => '5', 'p2' => '11'])
            ->set('setScores.1', ['p1' => '5', 'p2' => '11'])
            ->call('submitScore');

        expect($semiA->fresh()->winner_id)->toBe($semiA->player1_id);
    });
});

/**
 * Two semi-finals of four fresh pairs, with their final and bronze match.
 *
 * @return array{0: TournamentMatch, 1: TournamentMatch}
 */
function semiFinalsOf(Tournament $tournament): array
{
    $final = TournamentMatch::create(['tournament_id' => $tournament->id, 'round' => 'final', 'match_order' => 1, 'status' => 'scheduled']);
    $bronze = TournamentMatch::create(['tournament_id' => $tournament->id, 'round' => 'bronze', 'match_order' => 1, 'status' => 'scheduled', 'is_bronze_match' => true]);

    return collect([1, 2])->map(function (int $order) use ($tournament, $final, $bronze): TournamentMatch {
        [$p, $q] = collect([0, 1])->map(function () use ($tournament): TournamentPair {
            $first = User::factory()->create();
            $second = User::factory()->create();
            $tournament->users()->attach([$first->id, $second->id], ['registration_status' => 'confirmed']);

            return $tournament->pairs()->create(['player1_id' => $first->id, 'player2_id' => $second->id, 'registered_by' => $first->id]);
        })->all();

        return TournamentMatch::create([
            'tournament_id' => $tournament->id, 'round' => 'semifinal', 'match_order' => $order, 'status' => 'scheduled',
            'next_match_id' => $final->id, 'bronze_match_id' => $bronze->id,
            'pair1_id' => $p->id, 'pair2_id' => $q->id, 'player1_id' => $p->player1_id, 'player2_id' => $q->player1_id,
        ]);
    })->all();
}

describe('forfeits', function (): void {
    it('gives the match to the side that is there, and frees the table', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b], 'tables' => [$one]] = liveDoubles();
        $match = matchBetween($t, $a, $b);

        launchOn(liveCentre($t), $match, $one)->call('forfeit', $match->id, 2);

        expect($match->fresh())
            ->status->toBe('completed')
            ->is_forfeit->toBeTrue()
            ->winner_id->toBe($match->player1_id)
            ->and(tableIsFree($t, $one))->toBeTrue();
    });

    it('withdraws a pair: its remaining matches are lost, it ends last, its played match stands', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b, $c]] = liveDoubles();
        $played = matchBetween($t, $a, $b);
        $played->recordResult([
            $played->pair1_id === $a->id ? ['player1_score' => 11, 'player2_score' => 5] : ['player1_score' => 5, 'player2_score' => 11],
            $played->pair1_id === $a->id ? ['player1_score' => 11, 'player2_score' => 5] : ['player1_score' => 5, 'player2_score' => 11],
        ]);

        liveCentre($t)->call('withdraw', $a->player2_id);

        $remaining = matchBetween($t, $a, $c)->fresh();
        $standings = app(TournamentMatchService::class)->calculatePoolStandings(Pool::where('tournament_id', $t->id)->first());
        expect($remaining->is_forfeit)->toBeTrue()
            ->and($remaining->winner_id)->toBe($c->player1_id)
            ->and($played->fresh()->winner_id)->toBe($a->player1_id)
            ->and($standings->last()['pair']->id)->toBe($a->id);
    });
});

it('takes a cash payment at the desk once the tournament is under way', function (): void {
    ['tournament' => $t, 'pairs' => [$a]] = liveDoubles();
    CashRegister::create(['name' => 'Caisse', 'held_by_user_id' => User::factory()->create()->id]);
    $registration = TournamentRegistration::where('tournament_id', $t->id)->where('user_id', $a->player1_id)->firstOrFail();
    $payment = $registration->payment()->create(['reference' => (new GeneratePaymentReference)(), 'amount_due' => 15, 'amount_paid' => 0, 'status' => 'pending']);
    $registration->update(['payment_id' => $payment->id]);

    liveCentre($t)->call('recordCashPayment', $a->player1_id);

    expect($registration->fresh()->payment->status)->toBe('paid');
});

describe('the score by QR code', function (): void {
    it('names both pairs in full on the table page', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b], 'tables' => [$one]] = liveDoubles();
        $match = matchBetween($t, $a, $b);
        launchOn(liveCentre($t), $match, $one);

        Livewire::actingAs(User::factory()->create())
            ->test('pages::club-events.tournaments.table-score-entry', ['tournament' => $t, 'table' => $one, 'match' => $match->fresh()])
            ->assertSee('A. Aone / A. Atwo')
            ->assertSee('B. Bone / B. Btwo');
    });

    it('keeps the first score entered, whoever sends the second', function (): void {
        ['tournament' => $t, 'pairs' => [$a, $b], 'tables' => [$one]] = liveDoubles();
        $match = matchBetween($t, $a, $b);
        $admin = User::factory()->isAdmin()->create();
        // The scorers' table opens the drawer while the match is being played…
        $live = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $t])
            ->call('openLaunchDrawer', $one->id)->call('startMatch', $match->id)
            ->call('openScoreEntry', $match->id, $one->id);
        $qr = Livewire::actingAs(User::factory()->create())
            ->test('pages::club-events.tournaments.table-score-entry', ['tournament' => $t, 'table' => $one, 'match' => $match->fresh()]);
        $second = Livewire::actingAs(User::factory()->create())
            ->test('pages::club-events.tournaments.table-score-entry', ['tournament' => $t, 'table' => $one, 'match' => $match->fresh()]);

        // …a player sends the score by QR, another one sends it again…
        $qr->set('setScores.0', ['p1' => '11', 'p2' => '5'])->set('setScores.1', ['p1' => '11', 'p2' => '5'])->call('submitScore');
        $second->set('setScores.0', ['p1' => '5', 'p2' => '11'])->set('setScores.1', ['p1' => '5', 'p2' => '11'])->call('submitScore');

        // …and the scorers' table sends what it had typed.
        $this->actingAs($admin);
        $live->set('setScores.0', ['p1' => '5', 'p2' => '11'])
            ->set('setScores.1', ['p1' => '5', 'p2' => '11'])
            ->call('submitScore');

        expect($match->fresh()->winner_id)->toBe($match->player1_id);
    });
});

describe('the results mail', function (): void {
    it('reaches the guardian of a child without an address, names the pair, and skips the absent', function (): void {
        Mail::fake();
        ['tournament' => $t, 'pairs' => [$a, $b, $c]] = liveDoubles();
        $child = User::find($a->player2_id);
        $child->update(['email' => null]);
        $child->guardians()->attach(Guardian::factory()->create(['email' => 'parent@example.test']));
        $absent = User::factory()->create();
        $t->users()->attach($absent->id, ['registration_status' => 'no_show']);
        TournamentMatch::where('tournament_id', $t->id)->get()->each(fn (TournamentMatch $m) => $m->recordResult([
            ['player1_score' => 11, 'player2_score' => 5], ['player1_score' => 11, 'player2_score' => 5],
        ]));

        liveCentre($t)
            ->set('activeTab', 'closure')
            ->set('sendThankYou', true)
            ->call('closeTournament');

        Mail::assertQueued(TournamentResultsMail::class, fn (TournamentResultsMail $mail): bool => $mail->hasTo('parent@example.test'));
        Mail::assertNotQueued(TournamentResultsMail::class, fn (TournamentResultsMail $mail): bool => $mail->recipient->is($absent));

        $rendered = new TournamentResultsMail($t, $child, 'Merci', 'Merci à tous', liveCentre($t)->get('rankings'))->render();
        expect($rendered)->toContain('Alpha Aone &amp; Aeta Atwo');
    });
});
