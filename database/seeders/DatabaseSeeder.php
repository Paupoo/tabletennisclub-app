<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\Ranking;
use Illuminate\Database\Seeder;
use App\Models\Club;
use App\Models\League;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\ForceIndex;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{

    protected $forceIndex = null;

    public function __construct(ForceIndex $forceIndex)
    {
        $this->forceIndex = $forceIndex;
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        League::create([
            'division' => '5E',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::MEN->value,
            'start_year' => 2023,
            'end_year' => 2024,
        ]);
        League::create([
            'division' => '5E',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::MEN->value,
            'start_year' => 2024,
            'end_year' => 2025,
        ]);

        League::create([
            'division' => '4B',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::MEN->value,
            'start_year' => 2024,
            'end_year' => 2025,
        ]);

        League::create([
            'division' => '3F',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::VETERANS->value,
            'start_year' => 2024,
            'end_year' => 2025,
        ]);

        Club::create([
            'name' => 'C.T.T Ottignies-Blocry',
            'licence' => 'BBW214',
            'street' => 'Rue de l\'invasion 80',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
        ]);


        // Create Z team team
        $team = Team::make([
            'name' => 'Z',
            ])
            ->club()->associate(Club::find(1))
            ->league()->associate(League::find(1));
        $team->save();

        // Create F team team
        $team = Team::make([
            'name' => 'F',
            ])
            ->club()->associate(Club::find(1))
            ->league()->associate(League::firstWhere('division', '4B'));
        $team->save();

        // // Create some matches for Z team

        // Create 1 admin
        $admin = User::make([
            'is_active' => true,
            'is_admin' => true,
            'is_competitor' => true,
            'email' => 'aurelien.paulus@gmail.com',
            'password' => Hash::make('test1234'),
            'first_name' => 'Aurélien',
            'last_name' => 'Paulus',
            'phone_number' => '0479577502',
            'birthday' => '1988-08-17 00:00:00',
            'street' => 'Rue de la chapelle 30',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'ranking' => 'E4',
            'licence' => '114399',
        ])->club()->associate(Club::first());
        $admin->save();
        $admin->teams()->attach(Team::firstWhere('name', 'Z'));     
        
        // Create test dream team

        // the players
        $players = [
            ['Olivier', 'Tilmans', 'E6', '223344', 'olivier.tilmans@test.com' ],
            ['Xavier', 'Coenen', 'E6', '123123', 'xavier.coenen@test.com' ],
            ['Arnaud', 'Ghysens', 'E2', '112233', 'arnaud.ghysens@test.com' ],
            ['Éric', 'Godart', 'E0', '443211', 'eric.godart@test.com' ],
            ['Sébastien', 'Vandevyver', 'E2', '987654', 'seba.vande@test.com' ],
            ['Dariusz', 'Sekula', 'E2', '332211', 'dariusz.sekula@test.com' ],
        ];

        foreach($players as $player) {
            $player = User::make([
                'is_active' => true,
                'is_admin' => false,
                'is_competitor' => true,
                'email' => $player[4],
                'password' => Hash::make('password'),
                'first_name' => $player[0],
                'last_name' => $player[1],
                'phone_number' => '047' . fake()->randomNumber(7, true),
                'birthday' => fake()->dateTimeBetween('-59 years', '-25 years'),
                'street' => fake()->streetAddress(),
                'city_code' => fake()->postcode(),
                'city_name' => fake()->city(),
                'ranking' =>  $player[2],
                'licence' => $player[3],
            ])->club()->associate(Club::first());
            $player->save();
            $player->teams()->attach(Team::firstWhere('name', 'Z'));
        }

        User::make([
            'is_active' => true,
            'is_admin' => false,
            'is_comittee_member' => true,
            'is_competitor' => true,
            'email' => 'thierry.regnier@gmail.com',
            'password' => Hash::make('password'),
            'first_name' => 'Thierry',
            'last_name' => 'Regnier',
            'phone_number' => '047' . fake()->randomNumber(7, true),
            'birthday' => fake()->dateTimeBetween('-59 years', '-25 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' =>  Ranking::D6->value,
            'licence' => '154856',
        ])->club()->associate(Club::first())->save();


        // Set ForceIndexes
        $this->forceIndex->setOrUpdateAll();
    }
}
