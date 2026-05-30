<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Trainings\Models;

use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Domains\Trainings\Models\TrainingPack;
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
            'season_id' => Season::factory(),
            'name' => fake()->words(3, true),
            // 'description' => fake()->sentence(),
            'price' => fake()->numberBetween(50, 200),
            'allow_discount' => true,
            'level' => fake()->randomElement(TrainingLevel::cases())->value,
            'type' => fake()->randomElement(TrainingType::cases())->value,
            'trainer_id' => User::factory(),
            'room_id' => Room::factory(),
            'is_open_enrollment' => false,
        ];
    }
}
