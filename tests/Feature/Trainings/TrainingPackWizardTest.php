<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;

/**
 * Un pack complet et valide au regard des règles de save().
 */
function editablePack(array $attributes = []): TrainingPack
{
    return TrainingPack::factory()->create(array_merge([
        'max_participants' => 5,
        'price' => 90,
        'type' => TrainingType::DIRECTED->value,
        'day_of_week' => 2,
        'start_time' => '18:00',
        'duration_minutes' => 90,
    ], $attributes));
}

describe('training pack wizard', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->isAdmin()->create();
    });

    it('leaves a withdrawn pack out of the offer when it is edited', function (): void {
        $pack = editablePack(['is_active' => false]);

        // Le wizard écrivait `is_active => true` en dur : changer le prix d'un
        // pack retiré le remettait en ligne sur le site public, sans rien dire.
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formPrice', 120)
            ->call('save');

        expect($pack->fresh()->is_active)->toBeFalse();
    })->group('training', 'pack');

    it('puts a freshly created pack in the offer', function (): void {
        $reference = editablePack();

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('openCreate')
            ->set('formSeasonId', $reference->season_id)
            ->set('formName', 'Jeudi Élite')
            ->set('formLevel', $reference->level->value)
            ->set('formType', TrainingType::DIRECTED->value)
            ->set('formTrainerId', $reference->trainer_id)
            ->set('formRoomId', $reference->room_id)
            ->set('formDayOfWeek', 4)
            ->set('formStartTime', '19:00')
            ->set('formDurationMinutes', 90)
            ->set('formPackStartDate', $reference->pack_start_date->toDateString())
            ->set('formPackEndDate', $reference->pack_end_date->toDateString())
            ->set('formPrice', 90)
            ->call('save');

        expect(TrainingPack::where('name', 'Jeudi Élite')->first()?->is_active)->toBeTrue();
    })->group('training', 'pack');

    it('closes the pack to self-service from the wizard', function (): void {
        $pack = editablePack();

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formEnrollmentsOpen', false)
            ->call('save');

        expect($pack->fresh()->enrollments_open)->toBeFalse();
    })->group('training', 'pack');

    it('reopens it from the actions menu without touching the offer', function (): void {
        $pack = editablePack(['enrollments_open' => false]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('toggleEnrollments', $pack->id);

        // Rouvrir les inscriptions ne remet pas un pack retiré dans l'offre :
        // les deux drapeaux sont indépendants, c'est toute leur raison d'être.
        expect($pack->fresh()->enrollments_open)->toBeTrue()
            ->and($pack->fresh()->is_active)->toBeTrue();
    })->group('training', 'pack');

    it('lets the committee add a member to a full pack, on purpose', function (): void {
        $pack = editablePack(['max_participants' => 1]);

        // SubscriptionFactory tire un membre existant au hasard : sans utilisateur
        // explicite, les deux inscriptions peuvent porter le même `user_id` et la
        // recherche par membre tombe alors sur le titulaire.
        $holder = Subscription::factory()->for($pack->season, 'season')->for(User::factory(), 'user')->create();
        $holder->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $latecomer = Subscription::factory()->for($pack->season, 'season')->for(User::factory(), 'user')->create();

        // Le plafond se franchit en connaissance de cause. L'interdire pousserait
        // le comité à gonfler max_participants, ce qui casserait la file pour de bon.
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('viewSessions', $pack->id)
            ->set('addMemberUserId', $latecomer->user_id)
            ->call('addMemberToPack');

        expect($latecomer->trainingPacks()->where('training_pack_id', $pack->id)->first()?->pivot->status)
            ->toBe('enrolled');
    })->group('training', 'pack');

    it('hands one session over to the coach who actually took it', function (): void {
        $pack = editablePack();
        $session = Training::factory()->past()->for($pack, 'trainingPack')->create([
            'trainer_id' => $pack->trainer_id,
        ]);
        $standIn = User::factory()->isCoach()->create();

        // Le titulaire est malade, un collègue prend la séance. Sans ce geste
        // la séance reste non pointée pour toujours : le remplaçant n'y a pas
        // accès et le titulaire n'y était pas.
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('viewSessions', $pack->id)
            ->call('reassignSessionCoach', $session->id, $standIn->id);

        expect($session->fresh()->trainer_id)->toBe($standIn->id)
            ->and($standIn->can('recordAttendance', $session->fresh()))->toBeTrue();
    })->group('training', 'attendance');

});
