<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Trainings\Models;

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingPack;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPack>
 */
class TrainingPackFactory extends Factory
{
    protected $model = TrainingPack::class;

    /**
     * A pack always covers a period — it is the pro rata's denominator.
     *
     * The default is a pack that has not started yet, which is the nominal case
     * {@see TrainingPackProrata::enrolmentStart()} describes: a member signing
     * up at registration, before the first session, owes the full price with no
     * pro rata involved. A pack already under way is what `started()` is for.
     *
     * The period deliberately does not follow the pack's season: SeasonFactory
     * picks a random year between 2024 and 2034, which would drop today on
     * either side of the period at random and make every billing test flaky.
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'pack_start_date' => CarbonImmutable::today()->addMonth()->startOfMonth()->toDateString(),
            'pack_end_date' => CarbonImmutable::today()->addMonths(10)->endOfMonth()->toDateString(),
            'name' => fake()->words(3, true),
            // 'description' => fake()->sentence(),
            'price' => fake()->numberBetween(50, 200),
            'allow_discount' => true,
            'level' => fake()->randomElement(TrainingLevel::cases())->value,
            'type' => fake()->randomElement(TrainingType::cases())->value,
            'trainer_id' => User::factory(),
            'room_id' => Room::factory(),
            'is_open_enrollment' => false,
            'enrollments_open' => true,
        ];
    }

    /**
     * Un pack retiré du libre-service : le comité peut encore y ajouter
     * quelqu'un, les membres ne peuvent plus s'y inscrire seuls.
     */
    public function enrolmentsClosed(): self
    {
        return $this->state(fn (): array => ['enrollments_open' => false]);
    }

    /**
     * A pack already under way: it began three months ago and runs for ten.
     * A member joining or leaving now is pro-rated on the months actually held.
     */
    public function started(): self
    {
        return $this->state(fn (): array => [
            'pack_start_date' => CarbonImmutable::today()->subMonths(3)->startOfMonth()->toDateString(),
            'pack_end_date' => CarbonImmutable::today()->addMonths(6)->endOfMonth()->toDateString(),
        ]);
    }
}
