<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Database\Seeder;

/**
 * Demo data for the planning board: ~30 members who want directed training,
 * with a realistic modest-club distribution — many kids/teens (mostly unranked
 * to E series) and fewer adults (E to D, capped around D4/D6).
 *
 * Idempotent: skips if the active season already has a directed-training cohort.
 */
class DirectedTrainingDemoSeeder extends Seeder
{
    /** Rankings for adults: E and D, capped at D4 (the strongest in this club). */
    private const ADULT_RANKINGS = ['NC', 'E6', 'E4', 'E4', 'E2', 'E2', 'E0', 'D6', 'D6', 'D4'];

    /** Rankings for kids/teens: mostly unranked (NC) up to low E, a rare D6. */
    private const YOUTH_RANKINGS = ['NC', 'NC', 'NC', 'NC', 'E6', 'E6', 'E4', 'E4', 'E2', 'E0', 'D6'];

    public function run(): void
    {
        $season = Season::active()->first() ?? Season::query()->latest('id')->first();

        if ($season === null) {
            $this->command?->warn('DirectedTrainingDemoSeeder: no season found, skipped.');

            return;
        }

        $existing = Subscription::query()
            ->where('season_id', $season->id)
            ->where('wants_directed_training', true)
            ->count();

        if ($existing >= 20) {
            $this->command?->info("DirectedTrainingDemoSeeder: season already has {$existing} directed-training members, skipped.");

            return;
        }

        // 10 children (7-12), 10 teens (13-17), 10 adults (18-55).
        $this->makeCohort($season, 10, 7, 12, self::YOUTH_RANKINGS);
        $this->makeCohort($season, 10, 13, 17, self::YOUTH_RANKINGS);
        $this->makeCohort($season, 10, 18, 55, self::ADULT_RANKINGS, adult: true);

        $this->command?->info('DirectedTrainingDemoSeeder: 30 directed-training members added for season ' . $season->name . '.');
    }

    /**
     * @param  list<string>  $rankings
     */
    private function makeCohort(Season $season, int $count, int $minAge, int $maxAge, array $rankings, bool $adult = false): void
    {
        for ($n = 0; $n < $count; $n++) {
            $user = User::factory()->create([
                'birthdate' => now()->subYears(fake()->numberBetween($minAge, $maxAge))->subDays(fake()->numberBetween(0, 364)),
                'ranking' => fake()->randomElement($rankings),
            ]);

            $canDrive = $adult && fake()->boolean(50);

            Subscription::factory()->create([
                'user_id' => $user->id,
                'season_id' => $season->id,
                'status' => 'paid',
                'wants_directed_training' => true,
                'is_competitive' => $adult ? fake()->boolean(40) : fake()->boolean(20),
                'can_drive' => $canDrive,
                'seats_available' => $canDrive ? fake()->numberBetween(2, 4) : null,
                'wants_to_be_captain' => $adult ? fake()->boolean(20) : false,
                'volunteer_help' => fake()->boolean(25),
            ]);
        }
    }
}
