<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingWaitlistOfferExpiredNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistSpotOfferedNotification;
use Illuminate\Support\Facades\DB;

class TrainingWaitlistService
{
    /** Pack sans plafond : toute la file est appelée. */
    private const int UNLIMITED = -1;

    /**
     * Passe en `expired` les offres dont le délai de confirmation est écoulé,
     * prévient les membres, puis rappelle la file des packs concernés.
     *
     * La ligne survit à l'expiration : la supprimer effacerait le fait que la
     * place avait été offerte, et le membre qui réclame n'aurait plus rien à
     * opposer. C'est ce que fait déjà le tournoi avec `cancelled`.
     *
     * @return int le nombre d'offres expirées
     */
    public function expireOffers(): int
    {
        $expired = DB::table('subscription_training_pack')
            ->where('status', 'offered')
            ->where('confirmation_deadline', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            return 0;
        }

        $packs = TrainingPack::whereIn('id', $expired->pluck('training_pack_id')->unique())
            ->get()
            ->keyBy('id');

        $subscriptions = Subscription::with('user')
            ->whereIn('id', $expired->pluck('subscription_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($expired as $offer) {
            DB::table('subscription_training_pack')
                ->where('id', $offer->id)
                ->update([
                    'status' => 'expired',
                    'waitlist_position' => null,
                    'confirmation_deadline' => null,
                ]);

            $pack = $packs->get($offer->training_pack_id);
            $user = $subscriptions->get($offer->subscription_id)?->user;

            if ($pack && $user) {
                $user->notify(new TrainingWaitlistOfferExpiredNotification($pack));
            }
        }

        $packs->each(fn (TrainingPack $pack): int => $this->releaseSpot($pack));

        return $expired->count();
    }

    /**
     * Offre les places libres du pack aux inscrits en attente, dans l'ordre.
     *
     * Point de passage unique : tout ce qui libère une place — départ, rejet
     * d'une demande, offre expirée, plafond relevé — appelle cette méthode et
     * rien d'autre. Elle recalcule elle-même le nombre de places disponibles,
     * si bien qu'un appelant n'a jamais à savoir combien il en a libérées.
     *
     * @return int le nombre d'offres envoyées
     */
    public function releaseSpot(TrainingPack $pack): int
    {
        $freeSpots = $this->freeSpots($pack);

        if ($freeSpots === 0) {
            return 0;
        }

        $next = DB::table('subscription_training_pack')
            ->where('training_pack_id', $pack->id)
            ->where('status', 'waiting')
            ->orderBy('waitlist_position')
            ->when($freeSpots > 0, fn ($query) => $query->limit($freeSpots))
            ->get();

        if ($next->isEmpty()) {
            return 0;
        }

        $deadline = now()->addHours(48);

        foreach ($next as $row) {
            DB::table('subscription_training_pack')
                ->where('id', $row->id)
                ->update([
                    'status' => 'offered',
                    'waitlist_position' => null,
                    'confirmation_deadline' => $deadline,
                ]);

            Subscription::find($row->subscription_id)?->user
                ->notify(new TrainingWaitlistSpotOfferedNotification($pack, $deadline));
        }

        $this->recalculatePositions($pack);

        return $next->count();
    }

    /**
     * Places réellement disponibles à l'instant T, ou self::UNLIMITED.
     *
     * Un pack sans plafond n'a plus de raison de faire attendre personne : on
     * appelle toute la file. La laisser en place la bloquerait pour toujours,
     * puisque plus aucun départ ne « libérera » de place sur un tel pack.
     */
    private function freeSpots(TrainingPack $pack): int
    {
        if ($pack->is_open_enrollment) {
            return self::UNLIMITED;
        }

        // `room` porte le plafond des packs qui n'en déclarent pas. Le service
        // est appelé depuis partout : il ne peut pas compter sur l'appelant
        // pour l'avoir chargée (LazyLoadingViolationException sinon).
        $max = $pack->loadMissing('room')->effectiveMaxParticipants();

        if ($max === 0) {
            return self::UNLIMITED;
        }

        return max(0, $max - $pack->committedCount());
    }

    /**
     * Renumérote la file à partir de 1 en préservant l'ordre relatif.
     */
    private function recalculatePositions(TrainingPack $pack): void
    {
        DB::table('subscription_training_pack')
            ->where('training_pack_id', $pack->id)
            ->where('status', 'waiting')
            ->orderBy('waitlist_position')
            ->get()
            ->each(function (object $row, int $index): void {
                DB::table('subscription_training_pack')
                    ->where('id', $row->id)
                    ->update(['waitlist_position' => $index + 1]);
            });
    }
}
