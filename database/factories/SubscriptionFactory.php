<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Choisir un utilisateur existant au hasard
        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        // Choisir une saison existante au hasard
        $season = Season::inRandomOrder()->first() ?? Season::factory()->create();

        $competitor = $this->faker->boolean(70); // 70% compétitif

        // Montant dû en fonction du type de licence
        $amountDue = $competitor ? 125 : 60;

        return [
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => $competitor,
            'amount_due' => $amountDue,
            'amount_paid' => 0,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => now(),
        ];
    }
}
