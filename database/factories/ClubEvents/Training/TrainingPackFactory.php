<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Training;

use App\Models\ClubEvents\Training\TrainingPack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPack>
 */
class TrainingPackFactory extends Factory
{
    protected $model = TrainingPack::class;

    public function definition(): array
    {
        return [
            'season_id' => \App\Models\ClubEvents\Interclub\Season::factory(),
            'name' => fake()->words(3, true),
            // 'description' => fake()->sentence(),
            'price' => fake()->numberBetween(50, 200),
            'level' => fake()->randomElement(\App\Enums\TrainingLevel::cases())->value,
            'type' => fake()->randomElement(\App\Enums\TrainingType::cases())->value,
            'trainer_id' => \App\Models\ClubAdmin\Users\User::factory(),
            'room_id' => \App\Models\ClubAdmin\Club\Room::factory(),
        ];
    }
}