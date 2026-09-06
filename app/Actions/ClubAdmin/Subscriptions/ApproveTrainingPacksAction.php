<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingPackProrata;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use Illuminate\Support\Facades\DB;

class ApproveTrainingPacksAction
{
    public function __construct(private readonly TrainingPackProrata $prorata = new TrainingPackProrata) {}

    /**
     * @param  int[]  $approvedPackIds
     */
    public function __invoke(Subscription $subscription, array $approvedPackIds, int $familyMembersCount = 1): void
    {
        $pendingRows = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->get();

        $packs = TrainingPack::whereIn('id', $pendingRows->pluck('training_pack_id'))->get()->keyBy('id');

        $released = [];

        foreach ($pendingRows as $row) {
            if (! in_array($row->training_pack_id, $approvedPackIds, true)) {
                $subscription->trainingPacks()->detach($row->training_pack_id);
                $released[] = $row->training_pack_id;

                continue;
            }

            $pack = $packs->get($row->training_pack_id);

            DB::table('subscription_training_pack')
                ->where('id', $row->id)
                ->update([
                    'status' => 'enrolled',
                    // La ligne devient facturable maintenant. On respecte la
                    // date de la demande quand elle existe : le membre ne doit
                    // pas gagner un mois parce que la validation a traîné.
                    'starts_on' => $row->starts_on ?? ($pack ? $this->prorata->enrolmentStart($pack) : null),
                ]);
        }

        (new CalculatePriceAction)($subscription, $familyMembersCount);

        // Une demande refusée rend sa place : la file doit être appelée, sinon
        // le pack reste affiché complet pendant que la place dort.
        $waitlist = app(TrainingWaitlistService::class);

        foreach ($packs->only($released) as $releasedPack) {
            $waitlist->releaseSpot($releasedPack);
        }
    }
}
