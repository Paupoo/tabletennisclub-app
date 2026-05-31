<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Competitions\Interclub\Models\Club;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Club::create([
            'name' => 'C.T.T Ottignies-Blocry',
            'licence' => config('app.club_licence'),
            'building_name' => 'Centre Sportif J. Demeester',
            'street' => "Rue de l'Invasion 80",
            'city_code' => '1340',
            'city_name' => 'Ottignies',
        ]);
    }
}
