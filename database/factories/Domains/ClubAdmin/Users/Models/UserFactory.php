<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Users\Models;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

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
            'has_key' => false,
            'email' => $uniqueEmail,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(array_column(Gender::cases(), 'name')),
            'phone_number' => fake()->numberBetween(460000000, 499000000),
            'birthdate' => fake()->dateTimeBetween('-75 years', '-18 years'),
            'street' => fake()->streetAddress(),
            'city_code' => (string) fake()->numberBetween(1000, 9999),
            'city_name' => fake()->city(),
            'ranking' => fake()->randomElement(array_column(Ranking::cases(), 'name')),
            'club_id' => 1,
        ];
    }

    public function isAdmin(): static
    {
        return $this->withRole(Role::ADMINISTRATOR);
    }

    public function isCoach(): static
    {
        return $this->withRole(Role::COACH);
    }

    public function isCommitteeMember(): static
    {
        return $this->withRole(Role::COMMITTEE);
    }

    public function isCompetitor(): static
    {
        return $this->state(function (array $attributes): array {
            $unusedLicence = fake()->numberBetween(95000, 170000);

            while (User::where('licence', $unusedLicence)->exists()) {
                $unusedLicence++;
            }

            return [
                'licence' => $unusedLicence,
                'ranking' => fake()->randomElement(array_column(Ranking::cases(), 'name')),
            ];
        })->afterCreating(function (User $user): void {
            $season = Season::current() ?? Season::inRandomOrder()->first();

            if ($season !== null) {
                // Statut confirmé : un compétiteur est d'abord un membre actif
                // du club (voir User::scopeCompetitor).
                Subscription::firstOrCreate(
                    ['user_id' => $user->id, 'season_id' => $season->id],
                    ['is_competitive' => true, 'amount_due' => 125, 'amount_paid' => 0, 'status' => 'confirmed'],
                );
            }
        });
    }

    public function isNotCompetitor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'licence' => null,
        ]);
    }

    public function isSelector(): static
    {
        return $this->withRole(Role::SELECTIONS);
    }

    public function minor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'birthdate' => fake()->dateTimeBetween('-17 years', '-8 years'),
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

    /**
     * Grants a role once the user exists — roles live in a pivot table, so unlike
     * the boolean columns they replace, they cannot be part of the insert.
     */
    public function withRole(Role ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles): void {
            $user->assignRole(...array_map(
                static fn (Role $role): string => $role->value,
                $roles,
            ));
        });
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
