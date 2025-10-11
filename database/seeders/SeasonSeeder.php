<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SeasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $thisYear = now()->format('Y');

        for ($i = 0; $i < 3; $i++) {
            Season::create([
                'name' => (string) ($thisYear + $i . '-' . $thisYear + $i + 1),
                'start_at' => Carbon::parse('First day of September ' . $thisYear + $i),
                'end_at' => Carbon::parse('Last day of June ' . $thisYear + $i + 1),
            ]);

        }
    }
}
