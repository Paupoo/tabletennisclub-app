<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Générer un montant aléatoire (6000 ou 12500 centimes)
        $amount = $this->faker->randomElement([6000, 12500]);

        // Générer un statut aléatoire (ici "pending" par défaut)
        $status = 'pending';

        // Référence unique
        $reference = sprintf(
            '%03d/%04d/%05d',
            $this->faker->numberBetween(0, 999),
            $this->faker->numberBetween(0, 9999),
            $this->faker->numberBetween(0, 99999)
        );
        
        // Date aléatoire sur les 30 derniers jours
        $createdAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $updatedAt = $createdAt;

        return [
            'reference' => $reference,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'status' => $status,
            'transaction_id' => null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }
}
