<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Payments\InviteToPayAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Support\PaymentCovers;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackAddedByClubNotification;
use App\Domains\Trainings\Services\TrainingPackProrata;
use Illuminate\Support\Facades\DB;

/**
 * Inscrit un membre dans un pack à la main, sur décision du comité.
 *
 * Ce n'est pas le pendant administrateur de {@see EnrollInTrainingPackAction},
 * c'est une autre politique : la place arrive validée et non `pending` — le
 * comité n'a pas à valider sa propre décision —, le verrou d'inscription ne
 * s'applique pas, et le plafond peut être franchi en connaissance de cause.
 *
 * Le complément éventuel est créé ici, mais pas réclamé : l'écran peut encore
 * poser une remise dessus, et l'invitation au paiement doit lire le solde net.
 * C'est à l'appelant de l'envoyer, avec {@see InviteToPayAction}.
 */
class AddMemberToTrainingPackAction
{
    /**
     * Affiliations auxquelles un pack peut être rattaché.
     *
     * @var list<string>
     */
    private const array BILLABLE_STATUSES = ['pending', 'confirmed', 'paid'];

    public function __construct(private readonly TrainingPackProrata $prorata = new TrainingPackProrata) {}

    /**
     * Renvoie le paiement complémentaire créé, ou null si rien n'est réclamé.
     */
    public function __invoke(
        Subscription $subscription,
        TrainingPack $pack,
        ?string $startsOn = null,
        int $familyMembersCount = 1,
    ): ?Payment {
        // Aligné sur Subscription::scopeAffiliated() : une affiliation annulée
        // ou remboursée n'a plus de facture ouverte à laquelle rattacher le pack.
        if (! in_array($subscription->status, self::BILLABLE_STATUSES, true)) {
            throw new \DomainException(__('This member has no active membership for the season.'));
        }

        // One piece: were the complement to fail, the member would be in the
        // pack at a raised price with nothing asked of them.
        $payment = DB::transaction(function () use ($subscription, $pack, $startsOn, $familyMembersCount): ?Payment {
            $existing = DB::table('subscription_training_pack')
                ->where('subscription_id', $subscription->id)
                ->where('training_pack_id', $pack->id)
                ->first();

            $attributes = [
                'status' => 'enrolled',
                'waitlist_position' => null,
                'confirmation_deadline' => null,
                'starts_on' => $this->prorata->enrolmentStart($pack, $startsOn),
                'ends_on' => null,
                'override_amount' => null,
                'override_reason' => null,
            ];

            if ($existing !== null) {
                $subscription->trainingPacks()->updateExistingPivot($pack->id, $attributes);
            } else {
                $subscription->trainingPacks()->attach($pack->id, $attributes);
            }

            // Ce que le membre devait avant : le complément est la différence, pas
            // le prix du pack — l'ajout peut faire jouer la remise multi-packs et
            // faire baisser un pack déjà facturé.
            $amountDueBefore = (float) $subscription->amount_due;

            // Facturer un membre à qui rien n'a encore été réclamé ferait payer
            // deux fois : sa cotisation initiale couvrira déjà ce pack.
            $alreadyInvoiced = $subscription->payments()->exists();

            (new CalculatePriceAction)($subscription, $familyMembersCount);

            $subscription->refresh();

            $complement = round((float) $subscription->amount_due - $amountDueBefore, 2);

            if ($alreadyInvoiced && $complement > 0) {
                return $subscription->payments()->create([
                    'reference' => (new GeneratePaymentReference)(),
                    'amount_due' => $complement,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'covers' => PaymentCovers::packs([$pack]),
                ]);
            }

            return null;
        });

        $subscription->user->notify(new TrainingPackAddedByClubNotification($pack, $subscription));

        return $payment;
    }
}
