<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackCancelledNotification;
use App\Domains\Trainings\Services\TrainingPackExit;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveTrainingPackAction
{
    /**
     * Retire le pack et renvoie le montant remboursable, en euros.
     *
     * Ce montant n'est *pas* le prix du pack. Quitter un pack peut faire perdre
     * la remise multi-packs de {@see CalculatePriceAction}, ce qui renchérit les
     * packs restants : rembourser le prix affiché rendrait alors trop d'argent.
     * On rembourse donc la baisse réelle du dû, plafonnée à ce que le membre a
     * effectivement versé — on ne rend jamais un euro qui n'est pas rentré.
     *
     * Le pro rata s'insère dans ce raisonnement sans le changer : une place
     * validée ne se détache plus, elle passe en `left` avec sa date de sortie.
     * {@see CalculatePriceAction} continue donc de facturer les mois consommés,
     * le nouveau dû ne retombe plus à zéro sur ce pack, et la baisse du dû —
     * seule mesure du remboursement — se réduit d'elle-même à la part non
     * consommée. Le plafond `netAmountPaid()` et l'effet de la remise perdue
     * jouent exactement comme avant, sur ce delta plus petit.
     *
     * `$endsOn` date le départ après coup — « il a arrêté fin octobre », encodé
     * en décembre. Jamais dans le futur : la ligne quitte tout de suite les
     * listes, elle ne peut pas être facturée jusqu'à une date à venir. Jamais
     * avant l'entrée ni avant la dernière séance où le coach l'a pointé. Les
     * absences écrites après cette date par la validation des séances sont
     * effacées : le membre n'était plus attendu.
     *
     * @throws \DomainException
     */
    public function __invoke(Subscription $subscription, TrainingPack $pack, int $familyMembersCount = 1, bool $notifyUser = true, ?string $endsOn = null): float
    {
        $pivot = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        if (! $pivot) {
            return 0.0;
        }

        // Déjà parti : rejouer le départ remettrait une date de sortie plus
        // tardive et referait grimper la facture.
        if ($pivot->status === 'left') {
            return 0.0;
        }

        $wasEnrolled = $pivot->status === 'enrolled';
        $wasPending = $pivot->status === 'pending';

        $exit = new TrainingPackExit;
        $departure = $endsOn !== null ? Carbon::parse($endsOn)->startOfDay() : Carbon::today();

        if ($wasEnrolled && $departure->isAfter(Carbon::today())) {
            throw new \DomainException(__('A departure cannot be dated in the future.'));
        }

        if ($wasEnrolled && $departure->isBefore($earliest = $exit->earliestDeparture($subscription, $pack))) {
            throw new \DomainException(__('A departure cannot be dated before :date: the member joined or was marked at a session that day.', [
                'date' => $earliest->format('d/m/Y'),
            ]));
        }

        $amountDueBefore = (float) $subscription->amount_due;

        if ($wasEnrolled) {
            // Une place validée a été consommée : on garde la ligne et on la
            // date. La supprimer effacerait le fait que le membre a participé.
            $subscription->trainingPacks()->updateExistingPivot($pack->id, [
                'status' => 'left',
                'ends_on' => $departure->toDateString(),
            ]);

            $exit->eraseAbsences($subscription, $pack, $departure);
        } else {
            // Demande jamais validée, liste d'attente, offre en cours : rien
            // n'a été facturé ni consommé, la ligne n'a aucune histoire à
            // raconter.
            $subscription->trainingPacks()->detach($pack->id);
        }

        if ($wasPending && $notifyUser) {
            $subscription->user->notify(new TrainingPackCancelledNotification($pack, $subscription));
        }

        (new CalculatePriceAction)($subscription, $familyMembersCount);

        // Toute sortie rend une place, `pending` comprise : c'est un statut que
        // committedCount() retient. Le service décide seul s'il y a quelqu'un à
        // appeler et combien — l'appelant n'a pas à le savoir.
        app(TrainingWaitlistService::class)->releaseSpot($pack);

        $subscription->refresh();

        if ($amountDueBefore <= (float) $subscription->amount_due) {
            return 0.0;
        }

        // Le dû a baissé : on réclame moins avant d'envisager de rendre. Tant que
        // rien n'est rentré, il n'y a rien à rendre — seulement une facture à
        // corriger, et c'est le cas courant d'un départ en début de saison.
        $overpaid = (new ReduceOutstandingInvoiceAction)($subscription);

        // netAmountPaid() rather than totalPaid(): the latter counts refund
        // payments as money coming in, so a second departure would refund the
        // same euros twice.
        return round(min($overpaid, $subscription->netAmountPaid()), 2);
    }
}
