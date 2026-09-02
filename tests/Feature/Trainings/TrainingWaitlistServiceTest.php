<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingWaitlistSpotOfferedNotification;
use App\Domains\Trainings\Services\TrainingWaitlistService;

/**
 * Attache une inscription en liste d'attente au rang donné.
 */
function waitingOn(TrainingPack $pack, int $position): Subscription
{
    $subscription = Subscription::factory()->create();

    $subscription->trainingPacks()->attach($pack->id, [
        'status' => 'waiting',
        'waitlist_position' => $position,
    ]);

    return $subscription;
}

/**
 * Statut du pivot d'une inscription sur un pack.
 */
function pivotStatusOn(Subscription $subscription, TrainingPack $pack): ?string
{
    return $subscription->trainingPacks()
        ->where('training_pack_id', $pack->id)
        ->first()?->pivot->status;
}

describe('TrainingWaitlistService::releaseSpot()', function (): void {
    it('offers the free spot to the first person waiting', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        $first = waitingOn($pack, 1);
        $second = waitingOn($pack, 2);

        $offers = app(TrainingWaitlistService::class)->releaseSpot($pack);

        expect($offers)->toBe(1)
            ->and(pivotStatusOn($first, $pack))->toBe('offered')
            ->and(pivotStatusOn($second, $pack))->toBe('waiting');

        Notification::assertSentTo($first->user, TrainingWaitlistSpotOfferedNotification::class);
    })->group('training', 'waitlist');

    it('offers every free spot at once, in waiting order', function (): void {
        Notification::fake();

        // Le cas du plafond relevé de 0 à 4 : quatre places s'ouvrent d'un coup.
        // Promouvoir une seule personne laisserait trois places vides pendant 48 h.
        $pack = TrainingPack::factory()->create(['max_participants' => 4, 'price' => 90]);

        $waiting = collect(range(1, 6))->map(fn (int $position): Subscription => waitingOn($pack, $position));

        $offers = app(TrainingWaitlistService::class)->releaseSpot($pack);

        expect($offers)->toBe(4)
            ->and(pivotStatusOn($waiting[0], $pack))->toBe('offered')
            ->and(pivotStatusOn($waiting[1], $pack))->toBe('offered')
            ->and(pivotStatusOn($waiting[2], $pack))->toBe('offered')
            ->and(pivotStatusOn($waiting[3], $pack))->toBe('offered')
            ->and(pivotStatusOn($waiting[4], $pack))->toBe('waiting')
            ->and(pivotStatusOn($waiting[5], $pack))->toBe('waiting');
    })->group('training', 'waitlist');

    it('calls everyone in when the pack loses its cap', function (): void {
        Notification::fake();

        // Un pack passé en libre-service n'a plus de raison de faire attendre
        // qui que ce soit. Les laisser en file les y bloquerait pour toujours :
        // plus rien ne libérera jamais de place sur un pack sans plafond.
        $pack = TrainingPack::factory()->create([
            'max_participants' => null,
            'is_open_enrollment' => true,
            'price' => 90,
        ]);

        $waiting = collect(range(1, 3))->map(fn (int $position): Subscription => waitingOn($pack, $position));

        $offers = app(TrainingWaitlistService::class)->releaseSpot($pack);

        expect($offers)->toBe(3)
            ->and(pivotStatusOn($waiting[0], $pack))->toBe('offered')
            ->and(pivotStatusOn($waiting[1], $pack))->toBe('offered')
            ->and(pivotStatusOn($waiting[2], $pack))->toBe('offered');
    })->group('training', 'waitlist');

    it('calls nobody while the pack is still full', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        $holder = Subscription::factory()->create();
        $holder->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waiting = waitingOn($pack, 1);

        expect(app(TrainingWaitlistService::class)->releaseSpot($pack))->toBe(0)
            ->and(pivotStatusOn($waiting, $pack))->toBe('waiting');

        Notification::assertNothingSent();
    })->group('training', 'waitlist');

    it('closes the gaps in the queue after promoting', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 2, 'price' => 90]);

        $waiting = collect(range(1, 4))->map(fn (int $position): Subscription => waitingOn($pack, $position));

        app(TrainingWaitlistService::class)->releaseSpot($pack);

        // Les rangs 3 et 4 deviennent 1 et 2 : un membre à qui l'on a annoncé
        // « 3e sur la liste » doit voir ce nombre baisser, pas rester figé.
        $positionOf = fn (Subscription $s): ?int => $s->trainingPacks()
            ->where('training_pack_id', $pack->id)->first()?->pivot->waitlist_position;

        expect($positionOf($waiting[2]))->toBe(1)
            ->and($positionOf($waiting[3]))->toBe(2);
    })->group('training', 'waitlist');
});
