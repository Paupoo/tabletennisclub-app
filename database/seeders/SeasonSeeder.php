<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeasonSeeder extends Seeder
{
    public function run(): void
    {
        // Seasons run from July to June: from July on, the current season
        // started this year, before July it started last year.
        $seasonStartYear = Season::defaultStartYearOf(now());

        DB::transaction(function () use ($seasonStartYear): void {
            for ($offset = 0; $offset <= 1; $offset++) {
                $startYear = $seasonStartYear + $offset;
                $name = $startYear . '-' . ($startYear + 1);

                if (Season::where('name', $name)->exists()) {
                    continue;
                }

                [$startAt, $endAt] = Season::defaultDates($startYear);

                Season::create([
                    'name' => $name,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'is_active' => false,
                    'affiliations_open' => false,
                ]);
            }

            // Activate the current season if none is active yet
            if (! Season::where('is_active', true)->exists()) {
                $currentName = $seasonStartYear . '-' . ($seasonStartYear + 1);
                Season::where('name', $currentName)->update(['is_active' => true]);
            }
        });
    }
}
