<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Notifications\TournamentPairNotification;
use App\Domains\Competitions\Tournament\Services\TournamentPairingService;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Jobs\SendTournamentPairNoticeJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function pairingTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::SETUP,
        'match_type' => 'double',
        'doubles_registration_mode' => 'club',
        'start_date' => '2026-11-29 13:00',
        'duration_minutes' => 180,
        'pool_size' => 4,
        'nb_pools' => 1,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'logistics_buffer_minutes' => 3,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ]);
}

function pairingWizard(Tournament $tournament): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
        ->set('step', 'pairs');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function pairingEntrant(Tournament $tournament, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

    return $user;
}

/**
 * The pairs as sorted pairs of first names, so a test reads who plays with whom.
 *
 * @return list<list<string>>
 */
function pairingNames(Tournament $tournament): array
{
    return $tournament->pairs()->with(['player1', 'player2'])->get()
        ->map(fn (TournamentPair $pair): array => collect([$pair->player1->first_name, $pair->player2->first_name])->sort()->values()->all())
        ->sortBy(fn (array $names): string => $names[0])
        ->values()
        ->all();
}

/**
 * Register two players and pair them.
 *
 * @return array{0: User, 1: User}
 */
function pairingPair(Tournament $tournament, string $first, string $second): array
{
    $a = pairingEntrant($tournament, ['first_name' => $first]);
    $b = pairingEntrant($tournament, ['first_name' => $second]);
    $tournament->pairs()->create(['player1_id' => $a->id, 'player2_id' => $b->id, 'registered_by' => $a->id]);

    return [$a, $b];
}

/**
 * The pair board alone, cut out of the wizard page: the registrations list
 * above it names the same players.
 */
function pairingBoardHtml(Testable $wizard): string
{
    $html = $wizard->html();
    $start = strpos($html, 'data-pair-board');

    return $start === false ? '' : substr($html, $start, strpos($html, 'data-pair-board-end') - $start);
}

describe('proposing pairs', function (): void {
    it('pairs the strongest with the weakest', function (): void {
        $tournament = pairingTournament();
        pairingEntrant($tournament, ['first_name' => 'Bea', 'ranking' => 'B0']);
        pairingEntrant($tournament, ['first_name' => 'Cyril', 'ranking' => 'C0']);
        pairingEntrant($tournament, ['first_name' => 'Dana', 'ranking' => 'D0']);
        pairingEntrant($tournament, ['first_name' => 'Emile', 'ranking' => 'E0']);

        app(TournamentPairingService::class)->propose($tournament, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Bea', 'Emile'], ['Cyril', 'Dana']]);
    });

    it('pairs the eldest with the youngest within the same ranking', function (): void {
        $tournament = pairingTournament();
        pairingEntrant($tournament, ['first_name' => 'Zoe', 'ranking' => 'NC', 'birthdate' => '1950-06-01']);
        pairingEntrant($tournament, ['first_name' => 'Bob', 'ranking' => 'NC', 'birthdate' => '2016-06-01']);
        pairingEntrant($tournament, ['first_name' => 'Yann', 'ranking' => 'NC', 'birthdate' => '1975-06-01']);
        pairingEntrant($tournament, ['first_name' => 'Alix', 'ranking' => 'NC', 'birthdate' => '2000-06-01']);

        app(TournamentPairingService::class)->propose($tournament, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Alix', 'Yann'], ['Bob', 'Zoe']]);
    });

    it('leaves the pairs already formed alone and the odd player on the bench', function (): void {
        $tournament = pairingTournament();
        $parent = pairingEntrant($tournament, ['first_name' => 'Parent', 'ranking' => 'B0']);
        $child = pairingEntrant($tournament, ['first_name' => 'Child', 'ranking' => 'NC']);
        $tournament->pairs()->create(['player1_id' => $parent->id, 'player2_id' => $child->id, 'registered_by' => $parent->id]);
        pairingEntrant($tournament, ['first_name' => 'Cyril', 'ranking' => 'C0']);
        pairingEntrant($tournament, ['first_name' => 'Dana', 'ranking' => 'D0']);
        pairingEntrant($tournament, ['first_name' => 'Odd', 'ranking' => 'E0']);

        $pairing = app(TournamentPairingService::class);
        $pairing->propose($tournament, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Child', 'Parent'], ['Cyril', 'Odd']])
            ->and($pairing->bench($tournament)->pluck('first_name')->all())->toBe(['Dana']);
    });

    it('puts a player of unknown age in the middle of their ranking', function (): void {
        $tournament = pairingTournament();
        pairingEntrant($tournament, ['first_name' => 'Unknown', 'ranking' => 'NC', 'birthdate' => null]);
        pairingEntrant($tournament, ['first_name' => 'Eldest', 'ranking' => 'NC', 'birthdate' => '1950-06-01']);
        pairingEntrant($tournament, ['first_name' => 'Middle', 'ranking' => 'NC', 'birthdate' => '1990-06-01']);
        pairingEntrant($tournament, ['first_name' => 'Young', 'ranking' => 'NC', 'birthdate' => '2010-06-01']);

        app(TournamentPairingService::class)->propose($tournament, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Eldest', 'Young'], ['Middle', 'Unknown']]);
    });
});

describe('moving a player on the board', function (): void {
    it('swaps two players of different pairs', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        [, $dora] = pairingPair($tournament, 'Carl', 'Dora');

        app(TournamentPairingService::class)->move($tournament, $anna, $dora, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Anna', 'Carl'], ['Ben', 'Dora']]);
    });

    it('swaps two players who hold the same place in their pairs', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        [$carl] = pairingPair($tournament, 'Carl', 'Dora');

        app(TournamentPairingService::class)->move($tournament, $anna, $carl, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Anna', 'Dora'], ['Ben', 'Carl']]);
    });

    it('pairs two players of the bench', function (): void {
        $tournament = pairingTournament();
        $eva = pairingEntrant($tournament, ['first_name' => 'Eva']);
        $finn = pairingEntrant($tournament, ['first_name' => 'Finn']);

        app(TournamentPairingService::class)->move($tournament, $eva, $finn, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Eva', 'Finn']]);
    });

    it('sends the partner to the bench when a paired player joins someone from the bench', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        $eva = pairingEntrant($tournament, ['first_name' => 'Eva']);

        $pairing = app(TournamentPairingService::class);
        $pairing->move($tournament, $anna, $eva, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Anna', 'Eva']])
            ->and($pairing->bench($tournament)->pluck('first_name')->all())->toBe(['Ben']);
    });

    it('lets a player from the bench take the place of a paired player', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        $eva = pairingEntrant($tournament, ['first_name' => 'Eva']);

        $pairing = app(TournamentPairingService::class);
        $pairing->move($tournament, $eva, $anna, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Ben', 'Eva']])
            ->and($pairing->bench($tournament)->pluck('first_name')->all())->toBe(['Anna']);
    });

    it('breaks the pair when a player is dropped on the bench', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');

        $pairing = app(TournamentPairingService::class);
        $pairing->move($tournament, $anna, null, User::factory()->create());

        expect(pairingNames($tournament))->toBe([])
            ->and($pairing->bench($tournament)->pluck('first_name')->sort()->values()->all())->toBe(['Anna', 'Ben']);
    });

    it('changes nothing when a player is dropped on their own partner', function (): void {
        $tournament = pairingTournament();
        [$anna, $ben] = pairingPair($tournament, 'Anna', 'Ben');

        app(TournamentPairingService::class)->move($tournament, $anna, $ben, User::factory()->create());

        expect(pairingNames($tournament))->toBe([['Anna', 'Ben']]);
    });
});

describe('starting over', function (): void {
    it('sends everyone back to the bench', function (): void {
        $tournament = pairingTournament();
        pairingPair($tournament, 'Anna', 'Ben');
        pairingPair($tournament, 'Carl', 'Dora');

        $pairing = app(TournamentPairingService::class);
        $pairing->resetAll($tournament);

        expect(pairingNames($tournament))->toBe([])
            ->and($pairing->bench($tournament))->toHaveCount(4);
    });
});

describe('once the tournament is launched', function (): void {
    it('refuses to touch the pairs', function (Closure $change): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        pairingEntrant($tournament, ['first_name' => 'Eva']);
        pairingEntrant($tournament, ['first_name' => 'Finn']);
        $tournament->update(['status' => TournamentStatusEnum::PENDING]);

        expect(fn () => $change(app(TournamentPairingService::class), $tournament, $anna))
            ->toThrow(LogicException::class);

        expect(pairingNames($tournament))->toBe([['Anna', 'Ben']]);
    })->with([
        'propose' => fn (TournamentPairingService $pairing, Tournament $tournament): mixed => $pairing->propose($tournament, User::factory()->create()),
        'move' => fn (TournamentPairingService $pairing, Tournament $tournament, User $anna): mixed => $pairing->move($tournament, $anna, null, User::factory()->create()),
        'start over' => fn (TournamentPairingService $pairing, Tournament $tournament): mixed => $pairing->resetAll($tournament),
    ]);
});

describe('a paired player withdrawing', function (): void {
    it('sends the partner back to the bench before the pools are drawn', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');

        app(TournamentService::class)->cancelRegistration($tournament, $anna);

        expect(pairingNames($tournament))->toBe([])
            ->and(app(TournamentPairingService::class)->bench($tournament)->pluck('first_name')->all())->toBe(['Ben']);
    });

    it('erases the draw when the pools are drawn but the tournament not launched', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        Pool::factory()->for($tournament)->create(['name' => 'A']);

        app(TournamentService::class)->cancelRegistration($tournament, $anna);

        expect(pairingNames($tournament))->toBe([])
            ->and($tournament->pools()->count())->toBe(0);
    });

    it('leaves the pair in its pool once the tournament is launched', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        Pool::factory()->for($tournament)->create(['name' => 'A']);
        $tournament->update(['status' => TournamentStatusEnum::PENDING]);

        app(TournamentService::class)->cancelRegistration($tournament, $anna);

        expect(pairingNames($tournament))->toBe([['Anna', 'Ben']]);
    });

    it('leaves no pair behind when everyone is unregistered at once', function (): void {
        $tournament = pairingTournament();
        pairingPair($tournament, 'Anna', 'Ben');

        app(TournamentService::class)->unregisterAllUsers($tournament);

        expect(pairingNames($tournament))->toBe([]);
    });
});

describe('drawing the pools', function (): void {
    it('refuses while someone is still on the bench', function (): void {
        $tournament = pairingTournament();
        pairingPair($tournament, 'Anna', 'Ben');
        pairingPair($tournament, 'Carl', 'Dora');
        pairingEntrant($tournament, ['first_name' => 'Odd']);

        pairingWizard($tournament)->call('generatePools');

        expect($tournament->pools()->count())->toBe(0);
    });

    it('draws them once everyone is paired', function (): void {
        $tournament = pairingTournament();
        pairingPair($tournament, 'Anna', 'Ben');
        pairingPair($tournament, 'Carl', 'Dora');
        pairingPair($tournament, 'Eve', 'Fred');

        pairingWizard($tournament)->call('generatePools');

        expect($tournament->pools()->count())->toBe(1);
    });
});

describe('the pair board in the wizard', function (): void {
    it('pairs two players dropped on each other', function (): void {
        $tournament = pairingTournament();
        $eva = pairingEntrant($tournament, ['first_name' => 'Eva']);
        $finn = pairingEntrant($tournament, ['first_name' => 'Finn']);

        pairingWizard($tournament)->call('movePlayer', $eva->id, $finn->id);

        expect(pairingNames($tournament))->toBe([['Eva', 'Finn']]);
    });

    it('ignores a player who is not registered to the tournament', function (): void {
        $tournament = pairingTournament();
        $eva = pairingEntrant($tournament, ['first_name' => 'Eva']);
        $stranger = User::factory()->create();

        pairingWizard($tournament)->call('movePlayer', $eva->id, $stranger->id);

        expect(pairingNames($tournament))->toBe([]);
    });

    it('keeps the pairs as they are once the tournament is launched, and says why', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        $tournament->update(['status' => TournamentStatusEnum::PENDING]);

        $wizard = pairingWizard($tournament)->call('movePlayer', $anna->id, null);

        expect(pairingNames($tournament))->toBe([['Anna', 'Ben']])
            ->and(pairingBoardHtml($wizard))->toContain('Tournoi lancé');
    });

    it('lets the committee rework the pairs after the pools are drawn, erasing the draw', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        pairingPair($tournament, 'Carl', 'Dora');
        pairingPair($tournament, 'Eve', 'Fred');
        $wizard = pairingWizard($tournament)->call('generatePools');
        expect($tournament->pools()->count())->toBe(1);
        $dora = User::where('first_name', 'Dora')->first();

        $wizard->call('movePlayer', $anna->id, $dora->id);

        expect(pairingNames($tournament))->toBe([['Anna', 'Carl'], ['Ben', 'Dora'], ['Eve', 'Fred']])
            ->and($tournament->pools()->count())->toBe(0);
    });

    it('proposes pairs for the bench, then starts over', function (): void {
        $tournament = pairingTournament();
        pairingEntrant($tournament, ['first_name' => 'Bea', 'ranking' => 'B0']);
        pairingEntrant($tournament, ['first_name' => 'Emile', 'ranking' => 'E0']);

        $wizard = pairingWizard($tournament)->call('proposePairs');
        expect(pairingNames($tournament))->toBe([['Bea', 'Emile']]);

        $wizard->call('resetPairs');
        expect(pairingNames($tournament))->toBe([]);
    });

    it('shows the ranking and the age on the tournament day of every player', function (): void {
        $tournament = pairingTournament();
        pairingEntrant($tournament, ['first_name' => 'Jules', 'last_name' => 'Benchwarmer', 'ranking' => 'D4', 'birthdate' => '2014-12-01']);

        expect(pairingBoardHtml(pairingWizard($tournament)))
            ->toContain('Jules Benchwarmer')
            ->toContain('D4')
            ->toContain('11 ans');
    });

    it('shows the board for a doubles tournament whose pairing mode was never set', function (): void {
        $tournament = pairingTournament();
        $tournament->update(['doubles_registration_mode' => null]);
        pairingEntrant($tournament, ['first_name' => 'Jules', 'last_name' => 'Benchwarmer']);

        expect(pairingBoardHtml(pairingWizard($tournament)))->toContain('Jules Benchwarmer');
    });
});

/**
 * The players a pair notice was queued for, by first name.
 *
 * @return list<string>
 */
function pairingNoticedNames(): array
{
    $names = [];
    Bus::assertDispatched(SendTournamentPairNoticeJob::class, function (SendTournamentPairNoticeJob $job) use (&$names): bool {
        $names[] = User::find($job->userId)->first_name;

        return true;
    });
    sort($names);

    return $names;
}

describe('publishing the pairs', function (): void {
    it('tells every paired player who they play with', function (): void {
        Bus::fake();
        $tournament = pairingTournament();
        pairingPair($tournament, 'Anna', 'Ben');
        pairingPair($tournament, 'Carl', 'Dora');

        pairingWizard($tournament)->call('publishPairs');

        expect($tournament->fresh()->pairs_published_at)->not->toBeNull()
            ->and(pairingNoticedNames())->toBe(['Anna', 'Ben', 'Carl', 'Dora']);
    });

    it('tells no one while the pairs are still a draft', function (): void {
        Bus::fake();
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        $eva = pairingEntrant($tournament, ['first_name' => 'Eva']);

        app(TournamentPairingService::class)->move($tournament, $eva, $anna, User::factory()->create());

        Bus::assertNotDispatched(SendTournamentPairNoticeJob::class);
    });

    it('then tells only the players whose partner changed', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        pairingPair($tournament, 'Carl', 'Dora');
        $eva = pairingEntrant($tournament, ['first_name' => 'Eva']);
        $tournament->update(['pairs_published_at' => now()]);
        Bus::fake();

        app(TournamentPairingService::class)->move($tournament, $eva, $anna, User::factory()->create());

        expect(pairingNoticedNames())->toBe(['Anna', 'Ben', 'Eva']);
    });

    it('tells the partner of a player who withdraws, and only them', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        $tournament->update(['pairs_published_at' => now()]);
        Bus::fake();

        app(TournamentService::class)->cancelRegistration($tournament, $anna);

        expect(pairingNoticedNames())->toBe(['Ben']);
    });

    it('names the partner in the mail, as the pair stands when it leaves', function (): void {
        Notification::fake();
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');

        new SendTournamentPairNoticeJob($tournament->id, $anna->id)->handle();

        Notification::assertSentTo($anna, TournamentPairNotification::class, fn (TournamentPairNotification $notification): bool => str_contains(implode(' ', $notification->toMail($anna)->introLines), 'Ben'));
    });

    it('tells a player left without a partner that the committee will find one', function (): void {
        Notification::fake();
        $tournament = pairingTournament();
        $eva = pairingEntrant($tournament, ['first_name' => 'Eva']);

        new SendTournamentPairNoticeJob($tournament->id, $eva->id)->handle();

        Notification::assertSentTo($eva, TournamentPairNotification::class, fn (TournamentPairNotification $notification): bool => $notification->partner === null);
    });
});

/**
 * « My registrations » as this member sees it.
 */
function pairingMyRegistrations(User $member): Testable
{
    return Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $member]);
}

describe('my registrations once registrations are closed', function (): void {
    beforeEach(function (): void {
        $this->travelTo('2026-10-16');
        makeActiveSeason();
    });

    it('still shows the tournament to a registered player, and not to anyone else', function (): void {
        $tournament = pairingTournament();
        $tournament->update(['name' => 'Tournoi de doubles du club']);
        $anna = pairingEntrant($tournament, ['first_name' => 'Anna']);

        pairingMyRegistrations($anna)
            ->assertSee('Tournoi de doubles du club')
            ->assertSee('Inscriptions fermées');

        pairingMyRegistrations(User::factory()->create())->assertDontSee('Tournoi de doubles du club');
    });

    it('names the partner once the pairs are published, not before', function (): void {
        $tournament = pairingTournament();
        [$anna] = pairingPair($tournament, 'Anna', 'Ben');
        User::where('first_name', 'Ben')->update(['last_name' => 'Partnerson']);

        pairingMyRegistrations($anna)->assertDontSee('Partnerson');

        $tournament->update(['pairs_published_at' => now()]);

        pairingMyRegistrations($anna)->assertSee('Partnerson');
    });
});
