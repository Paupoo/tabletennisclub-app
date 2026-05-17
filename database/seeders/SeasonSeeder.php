<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClubEvents\Interclub\Season;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeasonSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = (int) now()->format('Y');

        // Seasons are September–June. Determine which academic year is "current":
        // If we are past September of this year, current season started this year.
        // Otherwise the current season started last year.
        $seasonStartYear = now()->month >= 9 ? $currentYear : $currentYear - 1;

        DB::transaction(function () use ($seasonStartYear): void {
            for ($offset = 0; $offset <= 1; $offset++) {
                $startYear = $seasonStartYear + $offset;
                $name = $startYear . '-' . ($startYear + 1);

                if (Season::where('name', $name)->exists()) {
                    continue;
                }

                Season::create([
                    'name' => $name,
                    'start_at' => Carbon::create($startYear, 9, 1)->startOfDay(),
                    'end_at' => Carbon::create($startYear + 1, 6, 30)->endOfDay(),
                    'is_active' => false,
                    'registrations_open' => false,
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
