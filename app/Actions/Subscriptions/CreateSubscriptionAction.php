<?php

namespace App\Actions\Subscriptions;

use App\Models\Season;
use App\Models\Subscription;
use App\Models\User;

class CreateSubscriptionAction
{
    public function execute(User $user, Season $season, array $options = []): Subscription
    {
        // Vérifie qu'il n'existe pas déjà une subscription pour cette saison
        $existing = Subscription::where('user_id', $user->id)
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['canceled'])
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
