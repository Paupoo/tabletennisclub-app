<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackRequestedNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistJoinedNotification;
use App\Domains\Trainings\Services\TrainingPackProrata;
use Illuminate\Support\Facades\DB;

class EnrollInTrainingPackAction
{
    /**
     * Statuts qui racontent un passage terminé, et non un engagement en cours :
     * ils n'interdisent pas de se réinscrire.
     *
     * @var list<string>
     */
    private const array SPENT_STATUSES = ['left', 'expired'];

    public function __construct(private readonly TrainingPackProrata $prorata = new TrainingPackProrata) {}

    public function __invoke(Subscription $subscription, TrainingPack $pack, int $familyMembersCount = 1): string
    {
        if ($subscription->status === 'cancelled') {
            throw new \DomainException(__('Cannot enroll with a cancelled subscription.'));
        }

        // Le verrou ne vaut que pour le libre-service. Le comité passe par
        // AddMemberToTrainingPackAction, qui l'ignore délibérément : fermer
        // les inscriptions ferme la porte aux membres, pas au club.
        if (! $pack->enrollments_open) {
            throw new \DomainException(__('Enrolments are closed for this training pack.'));
        }

        $existing = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        // Already enrolled or waitlisted. A `left` or `expired` line is history,
        // not a commitment: it must not lock the member out of coming back.
        if ($existing && ! in_array($existing->status, self::SPENT_STATUSES, true)) {
            throw new \DomainException(__('Already enrolled or waitlisted for this training pack.'));
        }

        // Rejoindre un pack quitté réutilise la ligne au lieu d'en créer une
        // seconde : tout le reste du code suppose une ligne par couple
        // (inscription, pack) et `updateExistingPivot` les écraserait toutes.
        $reuseLeftRow = $existing !== null;

        if ($pack->hasAvailableSpot()) {
            $this->write($subscription, $pack, $reuseLeftRow, [
                'status' => 'pending',
                'waitlist_position' => null,
                'confirmation_deadline' => null,
                // Le pro rata part d'ici : tant que le pack n'a pas commencé la
                // date reste nulle (ligne « tout le pack », prix plein), et dès
                // qu'il a démarré l'arrivée est datée.
                'starts_on' => $this->prorata->enrolmentStart($pack),
            ]);

            // Notify only for mid-season adds (Flux B) — Flux A is covered by SubscriptionCreatedNotification
            if ($subscription->status === 'paid') {
                $subscription->user->notify(new TrainingPackRequestedNotification($pack, $subscription));
            }

            return 'pending';
        }

        $position = $pack->waitlistCount() + 1;

        // Pas de `starts_on` sur une place en attente : le membre n'est pas
        // dans le pack, et la date d'entrée sera celle de la confirmation.
        $this->write($subscription, $pack, $reuseLeftRow, [
            'status' => 'waiting',
            'waitlist_position' => $position,
            'confirmation_deadline' => null,
            'starts_on' => null,
        ]);

        $subscription->user->notify(new TrainingWaitlistJoinedNotification($pack, $position));

        return 'waiting';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(Subscription $subscription, TrainingPack $pack, bool $reuseLeftRow, array $attributes): void
    {
        // Le montant forcé et la date de sortie d'un séjour précédent ne
        // doivent surtout pas survivre au nouveau.
        $attributes += [
            'ends_on' => null,
            'override_amount' => null,
            'override_reason' => null,
        ];

        if ($reuseLeftRow) {
            $subscription->trainingPacks()->updateExistingPivot($pack->id, $attributes);

            return;
        }

        $subscription->trainingPacks()->attach($pack->id, $attributes);
    }
}
