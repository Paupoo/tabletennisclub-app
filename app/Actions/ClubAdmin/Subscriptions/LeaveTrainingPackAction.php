<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackCancelledNotification;
use Illuminate\Support\Facades\DB;

class LeaveTrainingPackAction
{
    /**
     * Retire le pack et renvoie le montant réellement remboursable, en euros.
     *
     * Ce montant n'est *pas* le prix du pack. Quitter un pack peut faire perdre
     * la remise multi-packs de {@see CalculatePriceAction}, ce qui renchérit les
     * packs restants : rembourser le prix affiché rendrait alors trop d'argent.
     * On compare donc ce que le membre a versé au nouveau dû, et on ne rend que
     * le trop-perçu — zéro s'il reste débiteur.
     */
    public function __invoke(Subscription $subscription, TrainingPack $pack, int $familyMembersCount = 1, bool $notifyUser = true): float
    {
        $pivot = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        if (! $pivot) {
            return 0.0;
        }

        $wasEnrolled = $pivot->status === 'enrolled';
        $wasPending = $pivot->status === 'pending';

        $subscription->trainingPacks()->detach($pack->id);

        if ($wasPending && $notifyUser) {
            $subscription->user->notify(new TrainingPackCancelledNotification($pack, $subscription));
        }

        (new CalculatePriceAction)($subscription, $familyMembersCount);

        // Promote next waitlisted user only if a confirmed spot was freed
        if ($wasEnrolled && $pack->waitlistCount() > 0) {
            (new PromoteFromTrainingWaitlistAction)($pack);
        }

        $subscription->refresh();

        return max(0.0, round($subscription->netAmountPaid() - (float) $subscription->amount_due, 2));
    }
}
