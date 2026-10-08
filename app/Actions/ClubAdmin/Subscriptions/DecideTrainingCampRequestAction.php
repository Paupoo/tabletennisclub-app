<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\Subscriptions\Notifications\TrainingPackRejectedNotification;
use App\Domains\Trainings\Notifications\TrainingCampEnrolledNotification;
use App\Domains\Trainings\Services\TrainingCampBilling;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use Illuminate\Support\Facades\DB;

/**
 * The committee's answer to a request on a stage that sorts its requests.
 *
 * Decided on the stage itself, never on the affiliation screen: the stage has
 * nothing to do with the affiliation's validation, and approving one must not
 * silently drop the other.
 */
final readonly class DecideTrainingCampRequestAction
{
    public function __construct(private TrainingCampBilling $billing = new TrainingCampBilling) {}

    /**
     * A pending request held its spot: accepting it never overflows the stage.
     *
     * @throws \DomainException
     */
    public function approve(SubscriptionTrainingPack $line): void
    {
        $this->assertPending($line);

        DB::transaction(function () use ($line): void {
            $line->forceFill(['status' => 'enrolled'])->save();
            $this->billing->invoice($line);
        });

        $line->subscription->user->notify(new TrainingCampEnrolledNotification($line->trainingPack, $line->getAmountDue()));
    }

    /**
     * Nothing was invoiced on a request: the line goes, and its spot with it.
     *
     * @throws \DomainException
     */
    public function reject(SubscriptionTrainingPack $line, string $message = ''): void
    {
        $this->assertPending($line);

        $line->loadMissing('subscription.user', 'subscription.season', 'trainingPack');
        $subscription = $line->subscription;
        $pack = $line->trainingPack;

        // A line that once carried money keeps its history; a fresh request has none.
        if ($line->payments()->exists()) {
            $line->forceFill(['status' => 'cancelled'])->save();
        } else {
            $line->delete();
        }

        $subscription->user->notify(new TrainingPackRejectedNotification($subscription, $pack, $message));

        app(TrainingWaitlistService::class)->releaseSpot($pack);
    }

    /**
     * @throws \DomainException
     */
    private function assertPending(SubscriptionTrainingPack $line): void
    {
        if ($line->status !== 'pending' || ! $line->invoiced_separately) {
            throw new \DomainException(__('This request has already been decided.'));
        }
    }
}
