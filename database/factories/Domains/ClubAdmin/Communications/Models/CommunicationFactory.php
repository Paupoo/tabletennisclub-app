<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Communications\Models;

use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Communication>
 */
class CommunicationFactory extends Factory
{
    protected $model = Communication::class;

    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'reply_to' => fake()->safeEmail(),
            'criteria' => ['base' => 'active', 'licences' => [], 'genders' => [], 'age_bands' => []],
            'member_count' => 0,
            'recipient_count' => 0,
            'sent_at' => now(),
        ];
    }
}
