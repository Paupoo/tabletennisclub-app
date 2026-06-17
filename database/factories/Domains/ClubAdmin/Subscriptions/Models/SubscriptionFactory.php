<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Subscriptions\Models;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

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
            'can_drive' => false,
            'seats_available' => null,
            'wants_to_be_captain' => false,
            'volunteer_help' => false,
            'wants_directed_training' => false,
            'amount_due' => $amountDue,
            'amount_paid' => 0,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Le membre peut conduire (covoiturage) avec un nombre de places donné.
     */
    public function driver(int $seats = 4): static
    {
        return $this->state(fn (array $attributes): array => [
            'can_drive' => true,
            'seats_available' => $seats,
        ]);
    }

    /**
     * Le membre se porte volontaire pour aider bénévolement.
     */
    public function volunteer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'volunteer_help' => true,
        ]);
    }

    /**
     * Le membre souhaite être capitaine d'équipe.
     */
    public function wantsCaptain(): static
    {
        return $this->state(fn (array $attributes): array => [
            'wants_to_be_captain' => true,
        ]);
    }

    /**
     * Le membre souhaite un entraînement dirigé (avec coach).
     */
    public function wantsDirectedTraining(): static
    {
        return $this->state(fn (array $attributes): array => [
            'wants_directed_training' => true,
        ]);
    }
}
