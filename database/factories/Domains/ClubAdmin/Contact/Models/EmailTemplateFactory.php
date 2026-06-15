<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Contact\Models;

use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * A template that applies a status to the contact once sent.
     */
    public function appliesStatus(string $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'apply_status' => $status,
        ]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::slug($this->faker->unique()->words(2, true), '_'),
            'name' => $this->faker->sentence(3),
            'subject' => $this->faker->sentence(5),
            'body' => 'Bonjour {{first_name}}, ' . $this->faker->paragraph(),
            'apply_status' => null,
            'is_questionnaire' => false,
            'is_active' => true,
        ];
    }

    /**
     * A template that is no longer offered to the secretary.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * A template oriented towards gathering missing information.
     */
    public function questionnaire(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_questionnaire' => true,
        ]);
    }
}
