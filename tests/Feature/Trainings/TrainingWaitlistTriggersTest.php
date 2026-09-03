<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingWaitlistSpotOfferedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Les endroits d'où une place se libère sans que personne n'ait pensé à
 * rappeler la file. Chacun de ces tests correspond à un trou constaté.
 */
describe('waiting list triggers', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->isAdmin()->create();
    });

    it('calls the next in line when the committee rejects a training request', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        $requester = Subscription::factory()->create(['status' => 'paid']);
        $requester->trainingPacks()->attach($pack->id, ['status' => 'pending']);

        $waiting = Subscription::factory()->for($requester->season, 'season')->create();
        $waiting->trainingPacks()->attach($pack->id, [
            'status' => 'waiting',
            'waitlist_position' => 1,
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.registrations')
            ->set('currentTrainingRequestId', $requester->id)
            ->set('rejectionMessage', 'Pack complet cette saison.')
            ->call('rejectTrainingRequest');

        expect($waiting->trainingPacks()->where('training_pack_id', $pack->id)->first()->pivot->status)
            ->toBe('offered');

        Notification::assertSentTo($waiting->user, TrainingWaitlistSpotOfferedNotification::class);
    })->group('training', 'waitlist');

    it('calls everyone the new cap makes room for when it is raised', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create([
            'max_participants' => 1,
            'price' => 90,
            'type' => TrainingType::DIRECTED->value,
            'day_of_week' => 2,
            'start_time' => '18:00',
            'duration_minutes' => 90,
        ]);

        $holder = Subscription::factory()->create();
        $holder->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waiting = collect(range(1, 4))->map(function (int $position) use ($pack): Subscription {
            $subscription = Subscription::factory()->create();
            $subscription->trainingPacks()->attach($pack->id, [
                'status' => 'waiting',
                'waitlist_position' => $position,
            ]);

            return $subscription;
        });

        // Le plafond passe de 1 à 4 : trois places s'ouvrent d'un coup, donc
        // trois personnes doivent être appelées — pas une, et pas zéro.
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formMaxParticipants', '4')
            ->call('save');

        $statusOf = fn (Subscription $s): ?string => $s->trainingPacks()
            ->where('training_pack_id', $pack->id)->first()?->pivot->status;

        expect($statusOf($waiting[0]))->toBe('offered')
            ->and($statusOf($waiting[1]))->toBe('offered')
            ->and($statusOf($waiting[2]))->toBe('offered')
            ->and($statusOf($waiting[3]))->toBe('waiting');
    })->group('training', 'waitlist');

});
