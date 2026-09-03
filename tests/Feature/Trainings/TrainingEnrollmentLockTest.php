<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;

describe('closed enrolments', function (): void {
    it('turns a member away from a pack whose enrolments are closed', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create([
            'max_participants' => 5,
            'price' => 90,
            'enrollments_open' => false,
        ]);

        expect(fn () => (new EnrollInTrainingPackAction)($subscription, $pack))
            ->toThrow(DomainException::class);

        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())
            ->toBeFalse();
    })->group('training', 'enrollment');

    it('lets a member in while the pack is open', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);

        expect((new EnrollInTrainingPackAction)($subscription, $pack))->toBe('pending');
    })->group('training', 'enrollment');

    it('will not even queue a member on a closed pack', function (): void {
        $subscription = Subscription::factory()->create();

        // Rien ne libérera jamais cette place tant que le pack est fermé :
        // faire attendre quelqu'un serait lui promettre un tour qui ne vient pas.
        $pack = TrainingPack::factory()->enrolmentsClosed()->create([
            'max_participants' => 1,
            'price' => 90,
        ]);

        $holder = Subscription::factory()->create();
        $holder->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        expect(fn () => (new EnrollInTrainingPackAction)($subscription, $pack))
            ->toThrow(DomainException::class);
    })->group('training', 'enrollment');

});
