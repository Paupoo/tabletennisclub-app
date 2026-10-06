<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackEntry>
 */
class FeedbackEntryFactory extends Factory
{
    protected $model = FeedbackEntry::class;

    public function anonymous(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'feedback_theme_id' => FeedbackTheme::factory(),
            'body' => fake()->sentence(12),
            'status' => FeedbackStatus::New,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['status' => FeedbackStatus::Read, 'read_at' => now()]);
    }
}
