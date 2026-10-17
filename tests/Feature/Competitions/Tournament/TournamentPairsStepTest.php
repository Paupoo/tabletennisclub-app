<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentPairingService;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Jobs\SendTournamentPairNoticeJob;
use Illuminate\Support\Facades\Bus;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * The pairs have a step of their own, between the registrations and the
 * launch, for every doubles tournament — pairs chosen by the players can be
 * reworked on the day as well. Before the day, a change tells the players it
 * concerns; on the day everyone is in the hall, and nobody is mailed.
 */

function pairsStepTournament(array $overrides = []): Tournament
{
    return paymentTournament(array_merge([
        'status' => TournamentStatusEnum::SETUP,
        'match_type' => 'double',
        'doubles_registration_mode' => 'club',
        'price' => 0,
        'max_users' => 16,
        'nb_pools' => 1,
        'pool_size' => 4,
        'nb_qualifiers_per_pool' => 2,
        'duration_minutes' => 180,
        'logistics_buffer_minutes' => 3,
        'sets_to_win' => 3,
        'start_date' => now()->addDays(10)->toDateString(),
    ], $overrides));
}

function pairsStepEntrant(Tournament $tournament, string $firstName, string $status = 'registered'): User
{
    $player = User::factory()->create(['first_name' => $firstName]);
    $tournament->users()->attach($player->id, ['registration_status' => $status]);

    return $player;
}

/**
 * @return array{0: User, 1: User}
 */
function pairsStepPair(Tournament $tournament, string $first, string $second): array
{
    $a = pairsStepEntrant($tournament, $first);
    $b = pairsStepEntrant($tournament, $second);
    $tournament->pairs()->create(['player1_id' => $a->id, 'player2_id' => $b->id, 'registered_by' => $a->id]);

    return [$a, $b];
}

function pairsStepWizard(Tournament $tournament, string $step): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
        ->set('step', $step);
}

describe('the pairs step', function (): void {
    it('comes between the registrations and the launch of a doubles tournament', function (string $mode): void {
        $keys = array_column(pairsStepWizard(pairsStepTournament(['doubles_registration_mode' => $mode]), '5')->instance()->wizardSteps(), 'key');

        expect($keys)->toBe(['1', '2', '3', '4', '5', 'pairs', '6']);
    })->with(['club', 'self']);

    it('does not exist in singles', function (): void {
        $keys = array_column(pairsStepWizard(pairsStepTournament(['match_type' => 'single', 'doubles_registration_mode' => null]), '5')->instance()->wizardSteps(), 'key');

        expect($keys)->toBe(['1', '2', '3', '4', '5', '6']);
    });

    it('holds the pair board, which the registrations step no longer does', function (): void {
        $tournament = pairsStepTournament();
        pairsStepPair($tournament, 'Anna', 'Ben');

        pairsStepWizard($tournament, 'pairs')->assertSeeHtml('data-pair-board');
        pairsStepWizard($tournament, '5')->assertDontSeeHtml('data-pair-board');
    });

    it('offers no publishing for pairs the players chose themselves', function (): void {
        $tournament = pairsStepTournament(['doubles_registration_mode' => 'self']);
        pairsStepPair($tournament, 'Anna', 'Ben');

        pairsStepWizard($tournament, 'pairs')
            ->assertSeeHtml('data-pair-board')
            ->assertDontSee(__('Publish the pairs'));
    });

    it('reminds the launch of the players still without a partner', function (): void {
        $tournament = pairsStepTournament();
        pairsStepPair($tournament, 'Anna', 'Ben');
        pairsStepEntrant($tournament, 'Odd');

        pairsStepWizard($tournament, '6')
            ->assertSee(trans_choice(':count player still has no partner: compose the pairs first.|:count players still have no partner: compose the pairs first.', 1, ['count' => 1]));
    });
});

describe('who is told when a pair changes', function (): void {
    it('tells the players concerned of a change to pairs they chose, before the day', function (): void {
        $tournament = pairsStepTournament(['doubles_registration_mode' => 'self']);
        [$anna] = pairsStepPair($tournament, 'Anna', 'Ben');
        $eva = pairsStepEntrant($tournament, 'Eva');
        Bus::fake();

        app(TournamentPairingService::class)->move($tournament, $eva, $anna, User::factory()->create());

        Bus::assertDispatchedTimes(SendTournamentPairNoticeJob::class, 3);
    });

    it('tells nobody on the tournament day', function (): void {
        $tournament = pairsStepTournament(['start_date' => today()->toDateString(), 'pairs_published_at' => now()->subWeek()]);
        [$anna] = pairsStepPair($tournament, 'Anna', 'Ben');
        $eva = pairsStepEntrant($tournament, 'Eva');
        Bus::fake();

        app(TournamentPairingService::class)->move($tournament, $eva, $anna, User::factory()->create());
        app(TournamentService::class)->markAbsent($tournament, $eva, excused: true);

        Bus::assertNotDispatched(SendTournamentPairNoticeJob::class);
    });
});

describe('the list of registrations', function (): void {
    it('is sorted by first name, then by last name', function (): void {
        $tournament = pairsStepTournament(['match_type' => 'single', 'doubles_registration_mode' => null]);
        foreach ([['Zoé', 'Albert'], ['Anne', 'Zorro'], ['Anne', 'Martin'], ['Émile', 'Dubois']] as [$first, $last]) {
            $tournament->users()->attach(User::factory()->create(['first_name' => $first, 'last_name' => $last])->id, ['registration_status' => 'registered']);
        }

        expect(pairsStepWizard($tournament, '5')->get('registrations')->pluck('name')->all())
            ->toBe(['Anne Martin', 'Anne Zorro', 'Émile Dubois', 'Zoé Albert']);
    });

    it('finds a player by name', function (): void {
        $tournament = pairsStepTournament(['match_type' => 'single', 'doubles_registration_mode' => null]);
        foreach (['Anne' => 'Albert', 'Marc' => 'Martin'] as $first => $last) {
            $tournament->users()->attach(User::factory()->create(['first_name' => $first, 'last_name' => $last])->id, ['registration_status' => 'registered']);
        }

        expect(pairsStepWizard($tournament, '5')->set('registrationSearch', 'mart')->get('registrations')->pluck('name')->all())
            ->toBe(['Marc Martin']);
    });

    it('shows one tab at a time, each with its count', function (): void {
        $tournament = pairsStepTournament(['match_type' => 'single', 'doubles_registration_mode' => null, 'price' => 10]);
        $statuses = ['Anne' => 'registered', 'Marc' => 'registered', 'Zoé' => 'confirmed', 'Paul' => 'no_show'];
        foreach ($statuses as $first => $status) {
            $tournament->users()->attach(User::factory()->create(['first_name' => $first])->id, ['registration_status' => $status, 'has_paid' => $first === 'Zoé']);
        }

        $wizard = pairsStepWizard($tournament, '5');

        expect($wizard->get('registrationTabCounts'))->toBe(['all' => 4, 'to_check' => 2, 'present' => 1, 'absent' => 1, 'unpaid' => 3])
            ->and($wizard->set('registrationTab', 'to_check')->get('registrations'))->toHaveCount(2)
            ->and($wizard->set('registrationTab', 'unpaid')->get('registrations'))->toHaveCount(3);
    });
});

it('names each absence for what it is, in the words of its button', function (): void {
    $tournament = pairsStepTournament(['match_type' => 'single', 'doubles_registration_mode' => null, 'price' => 10]);
    foreach (['Anne' => true, 'Marc' => false] as $first => $excused) {
        $tournament->users()->attach(User::factory()->create(['first_name' => $first])->id, [
            'registration_status' => 'no_show', 'absence_excused' => $excused,
        ]);
    }

    $badges = pairsStepWizard($tournament, '5')->get('registrations')->pluck('status_badge.label', 'name');

    expect($badges->filter(fn (string $label): bool => $label === __('Excused absence')))->toHaveCount(1)
        ->and($badges->filter(fn (string $label): bool => $label === __('Unexcused absence')))->toHaveCount(1);
});

it('offers a late entry once registrations are closed and the cap is reached, until the launch', function (): void {
    $tournament = pairsStepTournament(['max_users' => 2]);
    pairsStepPair($tournament, 'Anna', 'Ben');

    pairsStepWizard($tournament, '5')->assertSeeHtml('data-register-member');

    $tournament->update(['status' => TournamentStatusEnum::PENDING]);
    pairsStepWizard($tournament, '5')->assertDontSeeHtml('data-register-member');
});

it('says the late entry goes above the cap, not to the waiting list', function (): void {
    $tournament = pairsStepTournament(['max_users' => 2]);
    pairsStepPair($tournament, 'Anna', 'Ben');
    $late = User::factory()->create();
    $late->subscriptions()->create(['season_id' => makeActiveSeason()->id, 'status' => 'confirmed', 'amount_due' => 0, 'amount_paid' => 0]);

    pairsStepWizard($tournament, '5')
        ->set('showRegisterModal', true)
        ->set('memberToRegister', $late->id)
        ->assertSee(__('The committee enters this player above the cap.'))
        ->assertDontSee(__('This player will be added to the waiting list.'));
});
