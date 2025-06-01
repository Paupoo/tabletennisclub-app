<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Room::create([
            'name' => 'Demeester -1',
            'building_name' => 'Centre Sportif Jean Demeester',
            'street' => 'Rue de l\'invasion 80',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'floor' => '-1',
            'access_description' => fake()->text(150),
            'capacity_for_trainings' => 7,
            'capacity_for_interclubs' => 4,
        ])->clubs()->attach(1);

        Room::create([
            'name' => 'Demeester 0',
            'building_name' => 'Centre Sportif Jean Demeester',
            'street' => 'Rue de l\'invasion 80',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'floor' => '0',
            'access_description' => fake()->text(150),
            'capacity_for_trainings' => 5,
            'capacity_for_interclubs' => 4,
        ])->clubs()->attach(1);

        Room::create([
            'name' => 'Demeester 2',
            'building_name' => 'Centre Sportif Jean Demeester',
            'street' => 'Rue de l\'invasion 80',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'floor' => '0',
            'access_description' => fake()->text(150),
            'capacity_for_trainings' => 5,
            'capacity_for_interclubs' => 4,
        ])->clubs()->attach(1);

        Room::create([
            'name' => 'Blocry G3',
            'building_name' => 'Centre Sportif du Blocry',
            'street' => 'Place des sports 1',
            'city_code' => '1348',
            'city_name' => 'Louvain-la-Neuve',
            'access_description' => fake()->text(150),
            'capacity_for_trainings' => 12,
            'capacity_for_interclubs' => 0,
        ])->clubs()->attach(1);

        Room::factory()
            ->count(5)
            ->hasClubs(1)
            ->create();

    }
}
