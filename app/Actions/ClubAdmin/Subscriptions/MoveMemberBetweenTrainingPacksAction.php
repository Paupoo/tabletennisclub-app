<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackMovedNotification;
use App\Domains\Trainings\Services\TrainingPackProrata;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Déplace un membre d'un pack validé vers un autre, en un seul geste.
 *
 * Ce n'est volontairement pas la composition de {@see LeaveTrainingPackAction}
 * et de {@see AddMemberToTrainingPackAction}. Enchaîner les deux produirait, le
 * même jour et pour le même membre, une demande de remboursement suivie d'une
 * nouvelle ligne de paiement — deux mouvements de trésorerie à rapprocher à la
 * main pour un changement de groupe qui, à prix égal, ne coûte rien. Et deux
 * courriels, dont un annonçant un départ que le membre n'a pas décidé.
 *
 * Le prix n'est donc recalculé **qu'une fois**, à la fin, et on ne solde que le
 * net : complément si le dû monte, remboursable si il baisse, rien s'il ne
 * bouge pas.
 *
 * Les deux dates ne se touchent pas par hasard : la ligne quittée est datée du
 * jour, mais la nouvelle ne devient facturable qu'au **mois suivant**. Le pro
 * rata compte les mois calendaires entamés, bornes comprises, donc dater les
 * deux du même jour ferait compter le mois courant deux fois — un déplacement
 * entre deux packs de même prix coûterait alors un mois de plus au membre.
 * C'est l'invariant que ce découpage protège : à prix égal, déplacer ne coûte
 * rien.
 */
class MoveMemberBetweenTrainingPacksAction
{
    /**
     * Affiliations auxquelles un pack peut être rattaché.
     *
     * @var list<string>
     */
    private const array BILLABLE_STATUSES = ['pending', 'confirmed', 'paid'];

    public function __construct(private readonly TrainingPackProrata $prorata = new TrainingPackProrata) {}

    /**
     * Renvoie le montant remboursable, en euros, ou 0 si le déplacement ne
     * rend rien. Le complément éventuel, lui, est facturé ici : c'est la même
     * politique que {@see AddMemberToTrainingPackAction}, qui crée sa ligne de
     * paiement sans la déléguer à l'appelant.
     */
    public function __invoke(
        Subscription $subscription,
        TrainingPack $from,
        TrainingPack $to,
        int $familyMembersCount = 1,
    ): float {
        if ($from->is($to)) {
            throw new DomainException(__('This member is already in that pack.'));
        }

        if (! in_array($subscription->status, self::BILLABLE_STATUSES, true)) {
            throw new DomainException(__('This member has no active membership for the season.'));
        }

        $origin = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $from->id)
            ->first();

        // Seule une place validée se déplace. Une demande, une file d'attente ou
        // une offre en cours n'ont pas de place à emporter : les déplacer
        // reviendrait à valider dans le nouveau pack ce que personne n'a validé
        // dans l'ancien.
        if (! $origin || $origin->status !== 'enrolled') {
            throw new DomainException(__('Only a confirmed spot can be moved to another pack.'));
        }

        $destination = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $to->id)
            ->first();

        if ($destination !== null && in_array($destination->status, ['enrolled', 'pending', 'offered'], true)) {
            throw new DomainException(__('This member is already in that pack.'));
        }

        $today = Carbon::today()->toDateString();

        // Le mois courant est déjà acquis à l'ancien pack : TrainingPackProrata
        // compte les mois calendaires *entamés*, bornes comprises. Faire partir
        // le nouveau pack aujourd'hui compterait donc ce mois-ci deux fois, et
        // un déplacement entre deux packs de même prix coûterait un mois de plus
        // au membre. La facturation du nouveau pack commence au mois suivant ;
        // l'entraînement, lui, commence tout de suite.
        $billableFrom = Carbon::today()->startOfMonth()->addMonth()->toDateString();

        $amountDueBefore = (float) $subscription->amount_due;

        // Facturer un membre à qui rien n'a encore été réclamé ferait payer deux
        // fois : sa cotisation initiale couvrira déjà le nouveau pack.
        $alreadyInvoiced = $subscription->payments()->exists();

        DB::transaction(function () use ($subscription, $from, $to, $destination, $today, $billableFrom): void {
            // La ligne quittée est datée, jamais supprimée : le membre a bien
            // suivi ce pack jusqu'à aujourd'hui, et le pro rata le facture.
            $subscription->trainingPacks()->updateExistingPivot($from->id, [
                'status' => 'left',
                'ends_on' => $today,
            ]);

            $attributes = [
                'status' => 'enrolled',
                'waitlist_position' => null,
                'confirmation_deadline' => null,
                // Laisser le pro rata décider seul ferait démarrer la ligne au
                // début du pack, et le membre paierait des mois passés ailleurs.
                'starts_on' => $this->prorata->enrolmentStart($to, $billableFrom),
                'ends_on' => null,
                'override_amount' => null,
                'override_reason' => null,
            ];

            if ($destination !== null) {
                $subscription->trainingPacks()->updateExistingPivot($to->id, $attributes);
            } else {
                $subscription->trainingPacks()->attach($to->id, $attributes);
            }
        });

        (new CalculatePriceAction)($subscription, $familyMembersCount);

        // La place rendue dans l'ancien pack appelle la file d'attente. Le
        // service décide seul s'il y a quelqu'un à appeler et combien.
        app(TrainingWaitlistService::class)->releaseSpot($from);

        $subscription->refresh();

        $delta = round((float) $subscription->amount_due - $amountDueBefore, 2);

        $payment = null;

        if ($alreadyInvoiced && $delta > 0) {
            $payment = $subscription->payments()->create([
                'reference' => (new GeneratePaymentReference)(),
                'amount_due' => $delta,
                'amount_paid' => 0,
                'status' => 'pending',
            ]);
        }

        $subscription->user->notify(
            new TrainingPackMovedNotification($from, $to, $subscription, $payment?->reference)
        );

        // Même plafond que {@see LeaveTrainingPackAction} : on ne rend jamais un
        // euro qui n'est pas rentré.
        return $delta < 0
            ? round(min(-$delta, $subscription->netAmountPaid()), 2)
            : 0.0;
    }
}
