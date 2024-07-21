<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
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
        Season::create([
            'start_year' => 2023,
            'end_year' => 2024,
        ]);
        Season::create([
            'start_year' => 2024,
            'end_year' => 2025,
        ]);
        Season::create([
            'start_year' => 2025,
            'end_year' => 2026,
        ]);

        League::create([
            'division' => '5E',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::MEN->value,
        ]);

        Club::create([
            'name' => 'C.T.T Ottignies-Blocry',
            'licence' => 'BBW214',
            'street' => 'Rue de l\'invasion 80',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
        ]);

        // Create pool
        $team = Team::make([
            'letter' => 'pool',
        ]);
        $team->club()->associate(Club::find(1));
        $team->save();

        // Create Z team team
        $team = Team::make([
            'letter' => 'Z',
            ])
            ->club()->associate(Club::find(1))
            ->season()->associate(Season::find(2))
            ->league()->associate(League::find(1));
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
        ])->club()->associate(Club::first())->team()->associate(Team::firstWhere('letter', 'Z'));

        $admin->save();

        // foreach($competitions as $competition) {
        //     $admin->competitions()->attach($competition->id, [
        //         'is_subscribed' => false,
        //         'is_selected' => false,
        //         'has_played' => false,
        //     ]);
        // }
        
        
        // Create test dream team

        // the players
        $players = [
            ['Olivier', 'Tilmans', 'E6', '223344', 'olivier.tilmans@test.com' ],
            ['Xavier', 'Coenen', 'E6', '123123', 'xavier.coenen@test.com' ],
            ['Arnaud', 'Ghysens', 'E2', '112233', 'arnaud.ghysens@test.com' ],
            ['Éric', 'Godart', 'E0', '443211', 'eric.godart@test.com' ],
            ['Sébastien', 'Vandevyver', 'E2', '987654', 'seba.vande@test.com' ],
            ['Dariusz', 'Skula', 'E2', '332211', 'dariusz.sekula@test.com' ],
        ];

        foreach($players as $player) {
            $user = User::make([
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
            ])->club()->associate(Club::first())->team()->associate(Team::firstWhere('letter', 'Z'))->save();

        // // the matches
        // foreach($competitions as $competition) {
        //     $user->competitions()->attach($competition->id, [
        //         'is_subscribed' => false,
        //         'is_selected' => false,
        //         'has_played' => false,
        //     ]);
        // }

        }

        // Give a captain for Z test team

        // // Create 75 members
        // User::factory(75)->create();

        // // If a player is competitor and has no ranking, get one.
        // $affected = DB::table('users')
        // ->where('ranking', '=', null)
        // ->where('is_competitor',
        //     '=',
        //     true
        // )
        // ->update(['ranking' => 'NC']);

        // // Create the rooms
        // Room::create([
        //     'name' => 'Demeester /-1',
        //     'street' => 'Rue de l\'Invasion 80',
        //     'city_code' => '1340',
        //     'city_name'=> 'Ottignies-Louvain-la-Neuve',
        //     'building_name' => 'Centre Sportif Jean Demeester',
        //     'access_description'=> 'Salle au -1',
        //     'capacity_trainings' => 12,
        //     'capacity_matches' => 2,
        // ]);

        // Room::create([
        //     'name' => 'Demeester /0',
        //     'street' => 'Rue de l\'Invasion 80',
        //     'city_code' => '1340',
        //     'city_name'=> 'Ottignies-Louvain-la-Neuve',
        //     'building_name' => 'Centre Sportif Jean Demeester',
        //     'access_description'=> 'Salle au RDC',
        //     'capacity_trainings' => 8,
        //     'capacity_matches' => 1,
        // ]);

        // Room::create([
        //     'name' => 'Blocry G3',
        //     'street' => 'Place des sports, 1',
        //     'city_code' => '1340',
        //     'city_name'=> 'Ottignies-Louvain-la-Neuve',
        //     'building_name' => 'Complexe Sportif de Blocry',
        //     'access_description'=> 'Salle G3',
        //     'capacity_trainings' => 16,
        //     'capacity_matches' => 0,
        // ]);

        // // Set ForceIndexes
        // $this->forceIndex->setOrUpdateAll();
    }
}
