<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Users\Models;

use App\Domains\ClubAdmin\Users\Models\MemberDeparture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\DepartureReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberDeparture>
 */
class MemberDepartureFactory extends Factory
{
    protected $model = MemberDeparture::class;

    /**
     * The running season when there is one: a new season drawn at random
     * could overlap it, which `Season` refuses.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'season_id' => fn (): int => Season::current()->id ?? Season::factory()->create()->id,
            'left_on' => now()->toDateString(),
            'reason' => fake()->randomElement(DepartureReason::cases()),
            'note' => null,
            'recorded_by' => null,
        ];
    }
}
