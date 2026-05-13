<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;


class CreateSubscriptionAction
{
    public function execute(User $user, Season $season, array $options = []): Subscription
    {
        if (! $season->is_active) {
            throw new \DomainException('Cannot subscribe to an inactive season');
        }

        if (! $season->registrations_open) {
            throw new \DomainException('Registrations are currently closed');
        }
        
        // Vérifie qu'il n'existe pas déjà une subscription pour cette saison
        $existing = Subscription::where('user_id', $user->id)
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existing) {
            throw new \DomainException(
                'User already has a subscription for this season'
            );
        }

        // Créé la subscription avec les valeurs par défaut
        return Subscription::create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'status' => 'pending',
            'is_competitive' => $options['is_competitive'] ?? false,
            'has_other_family_members' => $options['has_other_family_members'] ?? false,
            'trainings_count' => $options['trainings_count'] ?? 0,
            'subscription_price' => $season->base_price ?? 0, // Prix de base de la saison
            'training_unit_price' => $season->training_price ?? 0,
            'amount_due' => 0, // Sera calculé lors de la confirmation
        ]);
    }
}
