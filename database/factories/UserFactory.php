<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Ranking;
use App\Enums\Sex;
use App\Models\User;
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
        $uniqueEmail = $this->uniqueEmail();

        return [
            'is_active' => true,
            'is_admin' => false,
            'is_committee_member' => false,
            'is_competitor' => fake()->randomElement([true, false]),
            'has_paid' => false,
            'email' => $uniqueEmail,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'sex' => fake()->randomElement(array_column(Sex::cases(), 'name')),
            'phone_number' => fake()->numberBetween(460000000, 499000000),
            'birthdate' => fake()->dateTimeBetween('-75 years', '- 8 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' => fake()->randomElement(array_column(Ranking::cases(), 'name')),
            'club_id' => 1,
        ];
    }

    public function isAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_admin' => true,
        ]);
    }

    public function isCommitteeMember(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_committee_member' => true,
        ]);
    }

    public function isCompetitor(): static
    {

        return $this->state(function (array $attributes) {
            $unusedLicence = fake()->numberBetween(95000, 170000);

            while (User::where('licence', $unusedLicence)->exists()) {
                $unusedLicence++;
            }

            return [
                'is_competitor' => true,
                'licence' => $unusedLicence,
                'ranking' => fake()->randomElement(array_column(Ranking::cases(), 'name')),
            ];
        });
    }

    public function isNotCompetitor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_competitor' => false,
            'licence' => null,
        ]);
    }

    public function setRanking(Ranking $ranking): static
    {
        return $this->state(fn (array $attributes): array => [
            'ranking' => $ranking,
        ]);
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

    private function uniqueEmail(): string
    {
        $email = (string) fake()->unique()->safeEmail();

        while (User::where('email', $email)->exists()) {
            $email = (string) fake()->unique()->safeEmail();
        }

        return $email;
    }
}
