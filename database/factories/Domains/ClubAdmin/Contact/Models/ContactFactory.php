<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Contact\Models;

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Domains\Shared\Enums\PlayerExperienceEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'interest' => $this->faker->randomElement(ContactReasonEnum::values()),
            'membership_family_members' => $this->faker->numberBetween(0, 10),
            'membership_competitors' => $this->faker->numberBetween(0, 10),
            'membership_training_sessions' => $this->faker->numberBetween(0, 10),
            'membership_total_cost' => $this->faker->numberBetween(60, 800),
            'message' => $this->faker->paragraph(),
        ];
    }

    /**
     * Fill the optional triage profile fields captured incrementally.
     */
    public function withProfile(): static
    {
        return $this->state(fn (array $attributes): array => [
            'age_category' => $this->faker->randomElement(AgeCategoryEnum::cases()),
            'experience' => $this->faker->randomElement(PlayerExperienceEnum::cases()),
            'wants_competition' => $this->faker->boolean(),
            'preferred_days' => $this->faker->randomElements(
                ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                $this->faker->numberBetween(1, 3),
            ),
            'family_can_drive' => $this->faker->boolean(),
        ]);
    }
}
