<?php

declare(strict_types=1);

namespace Database\Factories\ClubAdmin\Contact;

use App\Enums\ContactReasonEnum;
use App\Models\ClubAdmin\Contact\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
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
}
