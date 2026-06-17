<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubPosts\Models;

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPost>
 */
class EventPostFactory extends Factory
{
    protected $model = EventPost::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(ClubEventTypeEnum::cases());

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(2),
            'type' => $type,
            'status' => $this->faker->randomElement(EventPostStatusEnum::cases()),
            'event_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'start_time' => $this->faker->time('H:i'),
            'end_time' => $this->faker->optional(0.7)->time('H:i'),
            'location' => $this->faker->randomElement([
                'Demeester',
                'Salle Principale',
                'Salle d\'Entraînement A',
                'Centre Jeunesse',
                'Court Débutant',
                'Tous les Courts',
            ]),
            'price' => $this->faker->optional(0.6)->randomElement([
                'Gratuit',
                '25€',
                '15€',
                'Nourriture incluse',
                'Prix de saison',
            ]),
            'icon' => $type->getIcon(),
            'max_participants' => $this->faker->optional(0.4)->numberBetween(8, 100),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'featured' => $this->faker->boolean(10),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventPostStatusEnum::PUBLISHED,
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => $this->faker->dateTimeBetween('now', '+3 months'),
        ]);
    }
}
