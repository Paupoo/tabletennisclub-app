<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;

describe('coach screen', function (): void {
    it('keeps a session that has already started within reach', function (): void {
        $coach = User::factory()->isCoach()->create();
        $justStarted = Training::factory()->for($coach, 'trainer')->create([
            'start' => now()->subHour(),
            'end' => now()->addMinutes(30),
        ]);

        // L'écran ne listait que `start >= now()` : la séance disparaissait à
        // l'instant où elle commençait, donc le coach ne pouvait pointer
        // qu'*avant* qu'elle ait lieu. C'est la raison pour laquelle la donnée
        // d'assiduité était en pratique inexploitable.
        $ids = collect(Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->get('sessionsToRecord'))->pluck('id');

        expect($ids)->toContain($justStarted->id);
    })->group('training', 'attendance');

    it('brings back the sessions nobody ever counted', function (): void {
        $coach = User::factory()->isCoach()->create();
        $forgotten = Training::factory()->past(10)->for($coach, 'trainer')->create();
        $counted = Training::factory()->past(3)->counted($coach)->for($coach, 'trainer')->create();
        $cancelled = Training::factory()->past(5)->cancelledFree()->for($coach, 'trainer')->create();

        $ids = collect(Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->get('sessionsToRecord'))->pluck('id');

        expect($ids)->toContain($forgotten->id)
            ->and($ids)->not->toContain($counted->id)
            ->and($ids)->not->toContain($cancelled->id);
    })->group('training', 'attendance');
});

describe('a session belongs to its coach', function (): void {
    it('refuses to open another coach session', function (): void {
        $owner = User::factory()->isCoach()->create();
        $stranger = User::factory()->isCoach()->create();
        $session = Training::factory()->past()->for($owner, 'trainer')->create();

        Livewire::actingAs($stranger)
            ->test('pages::club-events.trainings.coach')
            ->call('viewSession', $session->id)
            ->assertForbidden();
    })->group('training', 'attendance', 'security');

    it('refuses to cancel another coach session', function (): void {
        $owner = User::factory()->isCoach()->create();
        $stranger = User::factory()->isCoach()->create();
        $session = Training::factory()->for($owner, 'trainer')->create();

        // Le pire des trois : l'annulation envoie les mails aux inscrits.
        Livewire::actingAs($stranger)
            ->test('pages::club-events.trainings.coach')
            ->set('selectedSessionId', $session->id)
            ->call('confirmCancel')
            ->assertForbidden();

        expect($session->fresh()->status)->toBe('scheduled');
    })->group('training', 'attendance', 'security');

    it('refuses to mark attendance on another coach session', function (): void {
        $owner = User::factory()->isCoach()->create();
        $stranger = User::factory()->isCoach()->create();
        $member = User::factory()->create();
        $session = Training::factory()->past()->for($owner, 'trainer')->create();

        Livewire::actingAs($stranger)
            ->test('pages::club-events.trainings.coach')
            ->set('selectedSessionId', $session->id)
            ->call('setAttendance', $member->id, 'present')
            ->assertForbidden();

        expect($session->trainees()->count())->toBe(0);
    })->group('training', 'attendance', 'security');
});

describe('closing the count', function (): void {
    it('signs the session and takes it off the to-do list', function (): void {
        $coach = User::factory()->isCoach()->create();
        $session = Training::factory()->past()->for($coach, 'trainer')->create();

        $component = Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->call('viewSession', $session->id)
            ->call('validateAttendance');

        $session->refresh();

        expect($session->attendance_taken_at)->not->toBeNull()
            ->and($session->attendance_taken_by)->toBe($coach->id)
            ->and(collect($component->get('sessionsToRecord'))->pluck('id'))->not->toContain($session->id);
    })->group('training', 'attendance');

    it('will not let a stranger close someone else count', function (): void {
        $owner = User::factory()->isCoach()->create();
        $stranger = User::factory()->isCoach()->create();
        $session = Training::factory()->past()->for($owner, 'trainer')->create();

        Livewire::actingAs($stranger)
            ->test('pages::club-events.trainings.coach')
            ->set('selectedSessionId', $session->id)
            ->call('validateAttendance')
            ->assertForbidden();
    })->group('training', 'attendance', 'security');
});

describe('someone who came without being enrolled', function (): void {
    it('lets the coach add them to the session', function (): void {
        $coach = User::factory()->isCoach()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $session = Training::factory()->past()->for($pack, 'trainingPack')->for($coach, 'trainer')->create();

        $walkIn = User::factory()->create();

        // La moitié de la finalité « contrôle » tient là-dedans : voir qui vient
        // sans payer, pas seulement qui paie sans venir.
        $component = Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->call('viewSession', $session->id)
            ->call('addAttendee', $walkIn->id);

        expect($session->trainees()->where('user_id', $walkIn->id)->first()?->pivot->status)->toBe('present')
            ->and(collect($component->get('walkIns'))->pluck('id'))->toContain($walkIn->id);
    })->group('training', 'attendance');

    it('does not count an enrolled member as a walk-in', function (): void {
        $coach = User::factory()->isCoach()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $session = Training::factory()->past()->for($pack, 'trainingPack')->for($coach, 'trainer')->create();

        $subscription = Subscription::factory()->for($pack->season, 'season')->for(User::factory(), 'user')->create();
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $component = Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->call('viewSession', $session->id)
            ->call('setAttendance', $subscription->user_id, 'present');

        expect(collect($component->get('walkIns'))->pluck('id'))->not->toContain($subscription->user_id);
    })->group('training', 'attendance');
});

describe('reaching a child through their guardian', function (): void {
    it('shows the coach the guardian name and phone', function (): void {
        $coach = User::factory()->isCoach()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $session = Training::factory()->past()->for($pack, 'trainingPack')->for($coach, 'trainer')->create();

        $child = Subscription::factory()
            ->for($pack->season, 'season')
            ->for(User::factory()->state(['birthdate' => now()->subYears(11)]), 'user')
            ->create();
        $child->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $guardian = Guardian::factory()->create([
            'first_name' => 'Isabelle',
            'last_name' => 'Vandenberghe',
            'phone' => '0475112233',
        ]);
        $child->user->guardians()->attach($guardian->id);

        // L'écran lisait `guardian_phone_number` et `phone_number`, deux
        // attributs qui n'existent pas sur Guardian : il affichait « Tuteur : »
        // suivi de rien, et jamais le nom. C'est pourtant le seul moyen de
        // joindre la famille d'un mineur depuis le bord de la table.
        Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->call('viewSession', $session->id)
            ->assertOk()
            ->assertSee('Isabelle Vandenberghe')
            ->assertSee('0475112233');
    })->group('training', 'attendance');

    it('falls back to the phone recorded on the member when no guardian is linked', function (): void {
        $coach = User::factory()->isCoach()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $session = Training::factory()->past()->for($pack, 'trainingPack')->for($coach, 'trainer')->create();

        // Le cas majoritaire en base : le numéro du tuteur est saisi sur la
        // fiche du membre, sans qu'aucun Guardian ne soit lié. Ne lire que la
        // relation laisserait la plupart des enfants injoignables.
        $child = Subscription::factory()
            ->for($pack->season, 'season')
            ->for(User::factory()->state([
                'birthdate' => now()->subYears(10),
                'guardian_phone_number' => '0498776655',
            ]), 'user')
            ->create();
        $child->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->call('viewSession', $session->id)
            ->assertOk()
            ->assertSee('0498776655');
    })->group('training', 'attendance');
});
