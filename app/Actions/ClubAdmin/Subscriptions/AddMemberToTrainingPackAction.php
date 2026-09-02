<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

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

    public function __invoke(
        Subscription $subscription,
        TrainingPack $pack,
        ?string $startsOn = null,
        int $familyMembersCount = 1,
    ): void {
        // Aligné sur Subscription::scopeAffiliated() : une affiliation annulée
        // ou remboursée n'a plus de facture ouverte à laquelle rattacher le pack.
        if (! in_array($subscription->status, self::BILLABLE_STATUSES, true)) {
            throw new \DomainException(__('This member has no active membership for the season.'));
        }

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

        (new CalculatePriceAction)($subscription, $familyMembersCount);

        // Le montant annoncé au membre doit être celui d'après recalcul, pas
        // celui d'avant : c'est la seule chose qui l'intéresse dans ce mail.
        $subscription->refresh();

        $subscription->user->notify(
            new TrainingPackAddedByClubNotification($pack, $subscription)
        );
    }
}
