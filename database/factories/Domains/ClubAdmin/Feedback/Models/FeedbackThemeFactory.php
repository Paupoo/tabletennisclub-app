<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackTheme>
 */
class FeedbackThemeFactory extends Factory
{
    protected $model = FeedbackTheme::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'position' => fake()->numberBetween(20, 200),
            'is_permanent' => false,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['hidden_at' => now()]);
    }
}
