<?php

namespace Database\Factories;

use App\Enums\Ranking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_active' => true,
            'is_admin' => false,
            'is_comittee_member' => false,
            'is_competitor' => fake()->randomElement([true, false]),
            'has_debt' => false,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone_number' => fake()->numberBetween(460000000,499000000),
            'birthday' => fake()->dateTimeBetween('-75 years', '- 8 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' => fake()->randomElement(array_column(Ranking::cases(), 'value')),
            'licence' => fake()->unique()->numberBetween(95000, 170000),
            'club_id' => 1,
            'team_id' => 1,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
