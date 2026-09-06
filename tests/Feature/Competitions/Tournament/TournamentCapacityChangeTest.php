<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Notifications\TournamentWaitlistSpotOpenedNotification;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const TOURNAMENT_WIZARD_COMPONENT = 'pages::club-events.tournaments.wizard';

/**
 * Remplit un tournoi et lui met une file d'attente numérotée.
 *
 * @return array{0: Collection<int, User>, 1: Collection<int, User>}
 */
function fillAndQueue(Tournament $tournament, int $active, int $waiting): array
{
    $registered = User::factory()->count($active)->create();
    $tournament->users()->attach($registered->pluck('id'), ['registration_status' => 'registered']);

    $queued = User::factory()->count($waiting)->create();
    foreach ($queued as $position => $user) {
        $tournament->users()->attach($user->id, [
            'registration_status' => 'waiting',
            'waitlist_position' => $position + 1,
        ]);
    }

    return [$registered, $queued];
}

/**
 * Le statut d'inscription d'un membre sur un tournoi.
 */
function registrationStatusOn(Tournament $tournament, User $user): ?string
{
    return TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->value('registration_status');
}

describe('TournamentService::releaseSpots()', function (): void {
    it('envoie autant d\'offres que de places ouvertes, pas une seule', function (): void {
        Notification::fake();
        Event::fake();

        $tournament = paymentTournament(['max_users' => 16]);
        [, $queued] = fillAndQueue($tournament, active: 16, waiting: 6);

        $tournament->update(['max_users' => 20]);

        $offers = (new TournamentService)->releaseSpots($tournament->fresh());

        expect($offers)->toBe(4);

        foreach ($queued->take(4) as $promoted) {
            expect(registrationStatusOn($tournament, $promoted))->toBe('spot_offered');
            Notification::assertSentTo($promoted, TournamentWaitlistSpotOpenedNotification::class);
        }

        foreach ($queued->skip(4) as $stillWaiting) {
            expect(registrationStatusOn($tournament, $stillWaiting))->toBe('waiting');
            Notification::assertNotSentTo($stillWaiting, TournamentWaitlistSpotOpenedNotification::class);
        }
    });

    it('renumérote la file après les promotions', function (): void {
        Notification::fake();
        Event::fake();

        $tournament = paymentTournament(['max_users' => 16]);
        [, $queued] = fillAndQueue($tournament, active: 16, waiting: 5);

        $tournament->update(['max_users' => 18]);
        (new TournamentService)->releaseSpots($tournament->fresh());

        $positions = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('registration_status', 'waiting')
            ->orderBy('waitlist_position')
            ->pluck('waitlist_position')
            ->all();

        expect($positions)->toBe([1, 2, 3])
            ->and(registrationStatusOn($tournament, $queued[2]))->toBe('waiting');
    });

    it('appelle toute la file quand le plafond passe à zéro', function (): void {
        Notification::fake();
        Event::fake();

        $tournament = paymentTournament(['max_users' => 16]);
        [, $queued] = fillAndQueue($tournament, active: 16, waiting: 7);

        $tournament->update(['max_users' => 0]);

        $offers = (new TournamentService)->releaseSpots($tournament->fresh());

        expect($offers)->toBe(7);

        foreach ($queued as $promoted) {
            expect(registrationStatusOn($tournament, $promoted))->toBe('spot_offered');
        }
    });

    it('ne fait rien quand rien ne s\'est ouvert', function (): void {
        Notification::fake();
        Event::fake();

        $tournament = paymentTournament(['max_users' => 16]);
        fillAndQueue($tournament, active: 16, waiting: 3);

        expect((new TournamentService)->releaseSpots($tournament))->toBe(0);

        Notification::assertNothingSent();
    });

    it('ne fait rien quand personne n\'attend', function (): void {
        Notification::fake();
        Event::fake();

        $tournament = paymentTournament(['max_users' => 0]);
        fillAndQueue($tournament, active: 4, waiting: 0);

        expect((new TournamentService)->releaseSpots($tournament))->toBe(0);

        Notification::assertNothingSent();
    });

    it('se tait quand le tournoi n\'accepte plus d\'inscriptions', function (): void {
        Notification::fake();
        Event::fake();

        $tournament = paymentTournament(['max_users' => 16, 'status' => TournamentStatusEnum::SETUP]);
        [, $queued] = fillAndQueue($tournament, active: 16, waiting: 3);

        $tournament->update(['max_users' => 24]);

        expect($tournament->fresh()->registrationsAreOpen())->toBeFalse()
            ->and((new TournamentService)->releaseSpots($tournament->fresh()))->toBe(0)
            ->and(registrationStatusOn($tournament, $queued[0]))->toBe('waiting');

        Notification::assertNothingSent();
    });

    it('ne désinscrit personne quand le plafond descend sous les inscrits', function (): void {
        Notification::fake();
        Event::fake();

        $tournament = paymentTournament(['max_users' => 18]);
        [$registered] = fillAndQueue($tournament, active: 18, waiting: 2);

        $tournament->update(['max_users' => 16]);
        (new TournamentService)->releaseSpots($tournament->fresh());

        expect($tournament->fresh()->activeRegistrationsCount())->toBe(18);

        foreach ($registered as $member) {
            expect(registrationStatusOn($tournament, $member))->toBe('registered');
        }

        Notification::assertNothingSent();
    });
});

describe('wizard : changement de plafond', function (): void {
    /**
     * Un comité capable d'enregistrer un tournoi.
     */
    function tournamentOrganiser(): User
    {
        return User::factory()->isAdmin()->create();
    }

    /**
     * Le wizard positionné sur un tournoi existant, étape configuration.
     */
    function wizardOn(Tournament $tournament, User $organiser): Testable
    {
        return Livewire::actingAs($organiser)
            ->test(TOURNAMENT_WIZARD_COMPONENT, ['tournament' => $tournament])
            ->set('step', '1');
    }

    it('demande confirmation avant d\'appeler la file, et n\'écrit rien tant qu\'elle manque', function (): void {
        Notification::fake();
        Event::fake();

        $organiser = tournamentOrganiser();
        $tournament = paymentTournament(['max_users' => 16]);
        fillAndQueue($tournament, active: 16, waiting: 4);

        $component = wizardOn($tournament, $organiser)
            ->set('maxUsersManual', true)
            ->set('maxUsers', 20)
            ->call('save');

        $component->assertSet('capacityModal', true);

        expect($tournament->fresh()->max_users)->toBe(16);
        Notification::assertNothingSent();
    });

    it('enregistre et envoie les offres une fois confirmé', function (): void {
        Notification::fake();
        Event::fake();

        $organiser = tournamentOrganiser();
        $tournament = paymentTournament(['max_users' => 16]);
        [, $queued] = fillAndQueue($tournament, active: 16, waiting: 4);

        wizardOn($tournament, $organiser)
            ->set('maxUsersManual', true)
            ->set('maxUsers', 18)
            ->call('save')
            ->call('confirmCapacityChange')
            ->assertSet('capacityModal', false);

        expect($tournament->fresh()->max_users)->toBe(18)
            ->and(registrationStatusOn($tournament, $queued[0]))->toBe('spot_offered')
            ->and(registrationStatusOn($tournament, $queued[1]))->toBe('spot_offered')
            ->and(registrationStatusOn($tournament, $queued[2]))->toBe('waiting');

        Notification::assertSentTo($queued[0], TournamentWaitlistSpotOpenedNotification::class);
    });

    it('n\'interrompt pas un enregistrement qui n\'ouvre aucune place', function (): void {
        Notification::fake();
        Event::fake();

        $organiser = tournamentOrganiser();
        $tournament = paymentTournament(['max_users' => 16]);
        fillAndQueue($tournament, active: 16, waiting: 4);

        wizardOn($tournament, $organiser)
            ->set('name', 'Tournoi de rentrée')
            ->call('save')
            ->assertSet('capacityModal', false);

        expect($tournament->fresh()->name)->toBe('Tournoi de rentrée');
        Notification::assertNothingSent();
    });

    it('annonce la surcapacité quand le plafond descend sous les inscrits', function (): void {
        $organiser = tournamentOrganiser();
        $tournament = paymentTournament(['max_users' => 18]);
        fillAndQueue($tournament, active: 18, waiting: 0);

        $component = wizardOn($tournament, $organiser)
            ->set('maxUsersManual', true)
            ->set('maxUsers', 16);

        expect($component->instance()->pendingOverCapacity)->toBe(2);

        $component->assertSee(__(':count active registrations for :max spots: the tournament stays over capacity. Nobody is unregistered.', [
            'count' => 18,
            'max' => 16,
        ]));
    });
});
