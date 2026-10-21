<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Notifications\TournamentUnexcusedAbsenceNotification;
use App\Domains\Competitions\Tournament\Services\TournamentPairingService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * The morning of the tournament: who is here, who is not, and the pairs that
 * follow from it. The pools are drawn after the roll call, so an absent player
 * frees their partner for the bench, and a pool is never kept with a pair that
 * no longer exists.
 */

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    Notification::fake();
});

function checkInTournament(array $overrides = []): Tournament
{
    return paymentTournament(array_merge([
        'status' => TournamentStatusEnum::SETUP,
        'match_type' => 'double',
        'doubles_registration_mode' => 'club',
        'price' => 15,
        'nb_pools' => 1,
        'pool_size' => 4,
        'start_date' => now()->addDays(10)->toDateString(),
    ], $overrides));
}

function checkInWizard(Tournament $tournament): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
        ->set('step', '5');
}

/**
 * A registered, unpaid player — through the service, so they carry the real payment.
 */
function checkInEntrant(Tournament $tournament, string $firstName, string $ranking = 'NC'): User
{
    $tournament->update(['status' => TournamentStatusEnum::PUBLISHED]);
    $player = User::factory()->create(['first_name' => $firstName, 'ranking' => $ranking]);
    app(TournamentService::class)->registerUser($tournament, $player);
    $tournament->update(['status' => TournamentStatusEnum::SETUP]);

    return $player;
}

/**
 * @return array{0: User, 1: User}
 */
function checkInPair(Tournament $tournament, string $first, string $second): array
{
    $a = checkInEntrant($tournament, $first);
    $b = checkInEntrant($tournament, $second);
    $tournament->pairs()->create(['player1_id' => $a->id, 'player2_id' => $b->id, 'registered_by' => $a->id]);

    return [$a, $b];
}

function checkInRegistration(Tournament $tournament, User $player): TournamentRegistration
{
    return TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $player->id)->firstOrFail();
}

function benchNames(Tournament $tournament): array
{
    return app(TournamentPairingService::class)->bench($tournament)->pluck('first_name')->sort()->values()->all();
}

describe('reworking the pairs once the pools are drawn', function (): void {
    it('erases the pools and their matches, then applies the change', function (): void {
        $tournament = checkInTournament();
        [$anna] = checkInPair($tournament, 'Anna', 'Ben');
        [, $dora] = checkInPair($tournament, 'Carl', 'Dora');
        checkInPair($tournament, 'Eve', 'Fred');
        $wizard = checkInWizard($tournament)->call('generatePools')->call('generateMatches');
        expect($tournament->matches()->count())->toBe(3);

        $wizard->call('movePlayer', $anna->id, $dora->id);

        expect($tournament->pools()->count())->toBe(0)
            ->and($tournament->matches()->count())->toBe(0)
            ->and(TournamentPair::where('tournament_id', $tournament->id)->where(fn ($q) => $q->where('player1_id', $anna->id)->orWhere('player2_id', $anna->id))->first()?->partnerOf($anna)->first_name)->toBe('Carl');
    });

    it('erases the pools when the committee unregisters a paired player', function (): void {
        $tournament = checkInTournament();
        [$anna] = checkInPair($tournament, 'Anna', 'Ben');
        checkInPair($tournament, 'Carl', 'Dora');
        checkInPair($tournament, 'Eve', 'Fred');
        $wizard = checkInWizard($tournament)->call('generatePools');

        $wizard->call('cancelUserRegistration', $anna->id);

        expect($tournament->pools()->count())->toBe(0)
            ->and(benchNames($tournament))->toBe(['Ben']);
    });

    it('cannot launch with matches whose pair has changed, even after a reload', function (): void {
        $tournament = checkInTournament();
        [$anna] = checkInPair($tournament, 'Anna', 'Ben');
        [, $dora] = checkInPair($tournament, 'Carl', 'Dora');
        checkInPair($tournament, 'Eve', 'Fred');
        checkInWizard($tournament)->call('generatePools')->call('generateMatches')->call('movePlayer', $anna->id, $dora->id);

        checkInWizard($tournament)->call('processLaunch');

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::SETUP);
    });
});

describe('the roll call', function (): void {
    it('frees the partner of an absent player for the bench', function (): void {
        $tournament = checkInTournament();
        [$anna] = checkInPair($tournament, 'Anna', 'Ben');

        checkInWizard($tournament)->call('markAbsent', $anna->id, true);

        expect($tournament->pairs()->count())->toBe(0)
            ->and(benchNames($tournament))->toBe(['Ben'])
            ->and(checkInRegistration($tournament, $anna)->registration_status)->toBe('no_show');
    });

    it('drops the payment of an excused absence', function (): void {
        $tournament = checkInTournament();
        $anna = checkInEntrant($tournament, 'Anna');

        checkInWizard($tournament)->call('markAbsent', $anna->id, true);

        expect(checkInRegistration($tournament, $anna)->payment->status)->toBe('cancelled');
        Notification::assertNotSentTo($anna, TournamentUnexcusedAbsenceNotification::class);
    });

    it('keeps the payment of an unexcused absence and says why', function (): void {
        $tournament = checkInTournament();
        $anna = checkInEntrant($tournament, 'Anna');

        checkInWizard($tournament)->call('markAbsent', $anna->id, false);

        expect(checkInRegistration($tournament, $anna)->payment->status)->toBe('pending');
        Notification::assertSentTo($anna, TournamentUnexcusedAbsenceNotification::class);
    });

    it('never touches the payment of an absent player who has paid', function (): void {
        $tournament = checkInTournament();
        $anna = checkInEntrant($tournament, 'Anna');
        $payment = checkInRegistration($tournament, $anna)->payment;
        (new AllocateTransactionAction)->credit($payment, 15.0, 'cash');

        checkInWizard($tournament)->call('markAbsent', $anna->id, false);

        expect($payment->fresh()->status)->toBe('paid');
        Notification::assertNotSentTo($anna, TournamentUnexcusedAbsenceNotification::class);
    });

    it('brings an absent player back to the bench while the pools are not drawn', function (): void {
        $tournament = checkInTournament();
        $anna = checkInEntrant($tournament, 'Anna');
        $wizard = checkInWizard($tournament)->call('markAbsent', $anna->id, true);

        $wizard->call('confirmPresence', $anna->id);

        expect(checkInRegistration($tournament, $anna)->registration_status)->toBe('confirmed')
            ->and(benchNames($tournament))->toBe(['Anna']);
    });
});

describe('a late entry by the committee', function (): void {
    it('registers a member although registrations are closed', function (): void {
        $tournament = checkInTournament();
        $late = User::factory()->create(['first_name' => 'Late']);

        checkInWizard($tournament)->set('memberToRegister', $late->id)->call('registerMember');

        expect(checkInRegistration($tournament, $late)->registration_status)->toBe('registered')
            ->and(benchNames($tournament))->toBe(['Late']);
    });

    it('lets the committee go past the cap', function (): void {
        $tournament = checkInTournament(['max_users' => 2]);
        checkInPair($tournament, 'Anna', 'Ben');
        $late = User::factory()->create(['first_name' => 'Late']);

        checkInWizard($tournament)->set('memberToRegister', $late->id)->call('registerMember');

        expect(checkInRegistration($tournament, $late)->registration_status)->toBe('registered');
    });

    it('asks a member entered on the tournament day for no payment by mail', function (): void {
        $tournament = checkInTournament(['start_date' => today()->toDateString()]);
        $late = User::factory()->create();

        checkInWizard($tournament)->set('memberToRegister', $late->id)->call('registerMember');

        expect(checkInRegistration($tournament, $late)->payment_id)->toBeNull();
    });

    it('refuses once the tournament is launched', function (): void {
        $tournament = checkInTournament(['status' => TournamentStatusEnum::PENDING]);
        $late = User::factory()->create();

        checkInWizard($tournament)->set('memberToRegister', $late->id)->call('registerMember');

        expect(TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $late->id)->exists())->toBeFalse();
    });
});

describe('drawing the pools', function (): void {
    it('refuses a pool of fewer than three pairs', function (): void {
        $tournament = checkInTournament(['nb_pools' => 2]);
        foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
            checkInPair($tournament, "{$letter}1", "{$letter}2");
        }

        checkInWizard($tournament)->call('generatePools');

        expect($tournament->pools()->count())->toBe(0);
    });

    it('draws as many pools as the saved configuration says, not the unsaved form', function (): void {
        $tournament = checkInTournament(['nb_pools' => 2]);
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $letter) {
            checkInPair($tournament, "{$letter}1", "{$letter}2");
        }

        checkInWizard($tournament)->set('nb_poules', 3)->call('generatePools');

        expect($tournament->pools()->count())->toBe(2);
    });
});

it('spreads the field over the pools in a snake', function (): void {
    $tournament = paymentTournament(['status' => TournamentStatusEnum::SETUP, 'match_type' => 'single']);
    $rankings = ['B0', 'B2', 'B4', 'B6', 'C0', 'C2', 'C4', 'C6'];
    foreach ($rankings as $ranking) {
        $tournament->users()->attach(User::factory()->create(['first_name' => $ranking, 'ranking' => $ranking])->id, ['registration_status' => 'registered']);
    }

    app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);

    $pools = Pool::where('tournament_id', $tournament->id)->orderBy('name')->with('users')->get()
        ->map(fn (Pool $pool): array => $pool->users->pluck('first_name')->sort()->values()->all())
        ->all();

    expect($pools)->toBe([['B0', 'B6', 'C0', 'C6'], ['B2', 'B4', 'C2', 'C4']]);
});

it('names a pair by initial and last name', function (): void {
    $pair = TournamentPair::factory()->create([
        'player1_id' => User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont'])->id,
        'player2_id' => User::factory()->create(['first_name' => 'Léa', 'last_name' => 'Dupont'])->id,
    ]);

    expect($pair->displayName())->toBe('J. Dupont / L. Dupont')
        ->and($pair->fullName())->toBe('Jean Dupont & Léa Dupont');
});

it('explains an unexcused absence without scolding, in the member language', function (): void {
    app()->setLocale('fr_BE');
    $tournament = checkInTournament();
    $anna = checkInEntrant($tournament, 'Anna');
    $payment = checkInRegistration($tournament, $anna)->payment;

    $mail = (string) new TournamentUnexcusedAbsenceNotification($tournament, $payment)->toMail($anna)->render();

    expect($mail)->toContain('15,00 €')
        ->and($mail)->toContain('partenaire')
        ->and($mail)->not->toContain('Your place was held');
});
