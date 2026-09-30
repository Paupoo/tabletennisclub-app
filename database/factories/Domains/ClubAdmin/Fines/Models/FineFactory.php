<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Fines\Models;

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fine>
 */
class FineFactory extends Factory
{
    protected $model = Fine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reason = fake()->randomElement(FineReason::cases());
        $eventDate = fake()->dateTimeBetween('-3 weeks', '-1 week');

        return [
            'user_id' => User::factory(),
            'issued_by' => User::factory(),
            'amount' => fake()->randomElement([10, 15, 25, 35, 50]),
            'reason' => $reason,
            'provincial_code' => $reason->provincialCode(),
            'event_date' => $eventDate,
            'event_label' => fake()->randomElement(['LA HULPE RIXENSART', 'CHAMP. SEN.', 'SET JET FLEUR BLEUE', 'IC PBBWH15/027']),
            'payment_deadline' => now()->addWeeks(2)->startOfDay(),
            'pedagogical_message' => fake()->paragraph(),
        ];
    }

    /** The deadline has gone: the fine only remains as history. */
    public function pastDeadline(): static
    {
        return $this->state(fn (): array => [
            'event_date' => now()->subWeeks(6)->startOfDay(),
            'payment_deadline' => now()->subWeeks(2)->startOfDay(),
        ]);
    }
}
