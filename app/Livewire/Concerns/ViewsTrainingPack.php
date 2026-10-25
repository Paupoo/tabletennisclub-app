<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Ce que la fiche d'un pack et chacun de ses onglets partagent : le pack
 * consulté, le droit de le changer, et l'affiliation d'un de ses membres.
 *
 * L'identifiant est verrouillé : un onglet ne doit pas pouvoir être retourné
 * vers un autre pack depuis le navigateur.
 */
trait ViewsTrainingPack
{
    #[Locked]
    public int $packId = 0;

    #[Computed]
    public function mayManage(): bool
    {
        return Gate::allows(Permission::TrainingsManage->value);
    }

    #[Computed]
    public function selectedPack(): ?TrainingPack
    {
        return TrainingPack::with(['room', 'trainer', 'level', 'season', 'eventPost', 'sparrings'])->find($this->packId);
    }

    /** L'affiliation de ce membre pour la saison du pack consulté. */
    private function rosterSubscription(int $userId): ?Subscription
    {
        $pack = $this->selectedPack;

        if (! $pack || $userId === 0) {
            return null;
        }

        return Subscription::with(['user', 'season', 'trainingPacks', 'payments'])
            ->where('user_id', $userId)
            ->where('season_id', $pack->season_id)
            ->first();
    }

    /**
     * Ni l'annonce ni l'invitation au paiement ne sont parties : le comité est
     * le seul canal qui reste pour prévenir le membre.
     *
     * Le composant hôte porte le trait `Toast` de Mary.
     */
    private function warnWhenUnreachable(User $member): void
    {
        if ($member->contactEmails() !== []) {
            return;
        }

        $this->warning(__('No email address on file for :name — hand them the payment details.', [
            'name' => $member->first_name . ' ' . $member->last_name,
        ]));
    }
}
