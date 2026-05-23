<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubEvents\Training\TrainingPack;
use App\Notifications\Training\TrainingPackRequestedNotification;
use App\Notifications\Training\TrainingWaitlistJoinedNotification;

class EnrollInTrainingPackAction
{
    public function __invoke(Subscription $subscription, TrainingPack $pack, int $familyMembersCount = 1): string
    {
        if ($subscription->status === 'cancelled') {
            throw new \DomainException(__('Cannot enroll with a cancelled subscription.'));
        }

        // Already enrolled or waitlisted
        if ($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists()) {
            throw new \DomainException(__('Already enrolled or waitlisted for this training pack.'));
        }

        if ($pack->hasAvailableSpot()) {
            $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

            // Notify only for mid-season adds (Flux B) — Flux A is covered by SubscriptionCreatedNotification
            if ($subscription->status === 'paid') {
                $subscription->user->notify(new TrainingPackRequestedNotification($pack, $subscription));
            }

            return 'pending';
        }

        $position = $pack->waitlistCount() + 1;
        $subscription->trainingPacks()->attach($pack->id, [
            'status' => 'waiting',
            'waitlist_position' => $position,
        ]);

        $subscription->user->notify(new TrainingWaitlistJoinedNotification($pack, $position));

        return 'waiting';
    }
}
