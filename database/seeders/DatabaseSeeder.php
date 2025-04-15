<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\Ranking;
use App\Enums\Sex;
use Illuminate\Database\Seeder;
use App\Models\Club;
use App\Models\League;
use App\Models\Room;
use App\Models\Season;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\ForceList;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{

    protected $forceList = null;
    private Tournament $tournament;

    public function __construct(ForceList $forceList)
    {
        $this->forceList = $forceList;
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Club::create([
            'name' => 'C.T.T Ottignies-Blocry',
            'licence' => config('app.club_licence'),
            'street' => 'Rue de l\'invasion 80',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
        ]);

        Club::create([
            'name' => 'Set-Jet Fleur Bleue',
            'licence' => 'BBW034',
            'street' => 'Avenue du Comté de Jette, 3',
            'city_code' => '1090',
            'city_name' => 'Jette',
        ]);

        Club::create([
            'name' => 'Logis Auderghem',
            'licence' => 'BBW165',
            'street' => 'Chaussée de Wavre, 1690',
            'city_code' => '1160',
            'city_name' => 'Auderghem',
        ]);

        Club::create([
            'name' => 'REP Nivellois',
            'licence' => 'BBW118',
            'street' => 'Rue des Heures Claires, 46',
            'city_code' => '1400',
            'city_name' => 'Nivelles',
        ]);

        Club::create([
            'name' => 'CTT Limal',
            'licence' => 'BBW123',
            'street' => 'Rue Charles Jaumotte, 156',
            'city_code' => '1300',
            'city_name' => 'Limal',
        ]);

        Club::create([
            'name' => 'TT Perwez',
            'licence' => 'BBW289',
            'street' => 'Rue du Presbytère, 5',
            'city_code' => '1360',
            'city_name' => 'Perwez',
        ]);

        Season::factory(10)->create();

        League::create([
            'division' => '5E',
            'level' => LeagueLevel::PROVINCIAL_BW->name,
            'category' => LeagueCategory::MEN->name,
            'season_id' => 1,

        ]);
        League::create([
            'division' => '5E',
            'level' => LeagueLevel::PROVINCIAL_BW->name,
            'category' => LeagueCategory::MEN->name,
            'season_id' => 2,

        ]);

        League::create([
            'division' => '4B',
            'level' => LeagueLevel::PROVINCIAL_BW->name,
            'category' => LeagueCategory::MEN->name,
            'season_id' => 3,

        ]);

        League::create([
            'division' => '3F',
            'level' => LeagueLevel::PROVINCIAL_BW->name,
            'category' => LeagueCategory::VETERANS->name,
            'season_id' => 4,

        ]);

        League::create([
            'division' => '4B',
            'level' => LeagueLevel::PROVINCIAL_BW->name,
            'category' => LeagueCategory::WOMEN->name,
            'season_id' => 5,
        ]);



        // Create Z team team
        $teamZ = Team::make([
            'name' => 'Z',
            ])
            ->club()->associate(Club::firstWhere('licence', config('app.club_licence')))
            ->league()->associate(League::find(1))
            ->season()->associate(Season::find(1));
        $teamZ->save();

        // Create F team team
        $teamF = Team::make([
            'name' => 'F',
            ])
            ->club()->associate(Club::find(1))
            ->league()->associate(League::firstWhere('division', '4B'))
            ->season()->associate(Season::find(1));
            $teamF->save();

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
            'sex' => Sex::MEN->name,
            'phone_number' => '0479577502',
            'birthdate' => '1988-08-17 00:00:00',
            'street' => 'Rue de la chapelle 30',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'ranking' => Ranking::E4->name,
            'licence' => '114399',
        ])->club()->associate(Club::firstWhere('licence', config('app.club_licence')));
        $admin->save();
        $admin->teams()->attach(Team::firstWhere('name', 'Z'));     
        
        // Create test dream team

        $password = Hash::make('password');
        // the players
        $players = [
            ['Olivier', 'Tilmans', Ranking::E6->name, '223344', 'olivier.tilmans@test.com',Sex::MEN->name],
            ['Xavier', 'Coenen', Ranking::E6->name, '123123', 'xavier.coenen@test.com',Sex::MEN->name],
            ['Arnaud', 'Ghysens', Ranking::E2->name, '112233', 'arnaud.ghysens@test.com',Sex::MEN->name],
            ['Éric', 'Godart', Ranking::E0->name, '443211', 'eric.godart@test.com',Sex::MEN->name],
            ['Sébastien', 'Vandevyver', Ranking::E2->name, '987654', 'seba.vande@test.com',Sex::MEN->name],
            ['Dariusz', 'Sekula', Ranking::E2->name, '332211', 'dariusz.sekula@test.com',Sex::MEN->name],
        ];

        foreach($players as $player) {
            $player = User::make([
                'is_active' => true,
                'is_admin' => false,
                'is_competitor' => true,
                'email' => $player[4],
                'password' => $password,
                'remember_token' => Str::random(10),
                'first_name' => $player[0],
                'last_name' => $player[1],
                'sex' => $player[5],
                'phone_number' => '047' . fake()->randomNumber(7, true),
                'birthdate' => fake()->dateTimeBetween('-59 years', '-25 years'),
                'street' => fake()->streetAddress(),
                'city_code' => fake()->postcode(),
                'city_name' => fake()->city(),
                'ranking' =>  $player[2],
                'licence' => $player[3],
            ])->club()->associate(Club::firstWhere('licence', config('app.club_licence')));
            $player->save();
            $player->teams()->attach(Team::firstWhere('name', 'Z'));
            
            // Promote Oliver captain of team Z
            if ($player->licence === '223344') {
                $teamZ->update([
                    'captain_id' => $player->id
                ]);
            }
        }


        // Add some random users

        User::make([
            'is_active' => true,
            'is_admin' => false,
            'is_comittee_member' => true,
            'is_competitor' => true,
            'email' => 'thierry.regnier@gmail.com',
            'password' => $password,
            'first_name' => 'Thierry',
            'last_name' => 'Regnier',
            'sex' => Sex::MEN->name,
            'phone_number' => '047' . fake()->randomNumber(7, true),
            'birthdate' => fake()->dateTimeBetween('-59 years', '-25 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' =>  Ranking::D6->name,
            'licence' => '154856',
        ])->club()->associate(Club::firstWhere('licence', config('app.club_licence')))->save();

        User::make([
            'is_active' => true,
            'is_admin' => false,
            'is_comittee_member' => true,
            'is_competitor' => true,
            'email' => 'manon.patigny@gmail.com',
            'password' => $password,
            'first_name' => 'Manon',
            'last_name' => 'Patigny',
            'sex' => Sex::WOMEN->name,
            'phone_number' => '047' . fake()->randomNumber(7, true),
            'birthdate' => fake()->dateTimeBetween('-59 years', '-25 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' =>  Ranking::D4->name,
            'licence' => '852364',
        ])->club()->associate(Club::first())->save();

        User::factory()->count(5)->create([
            'is_competitor' => false,
        ]);

        User::factory()->count(2)->create([
            'is_competitor' => true,
            'ranking' => 'NC'
        ]);

        User::factory()->count(2)->create([
            'is_competitor' => false,
            'ranking' => 'NC'
        ]);

        // Set ForceIndexes
        $this->forceList->setOrUpdateAll();

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
            'name' => 'Blocry G3',
            'building_name' => 'Centre Sportif du Blocry',
            'street' => 'Place des sports 1',
            'city_code' => '1348',
            'city_name' => 'Louvain-la-Neuve',
            'access_description' => fake()->text(150),
            'capacity_for_trainings' => 12,
            'capacity_for_interclubs' => 0,
        ])->clubs()->attach(1);

        User::factory()
            ->count(50)
            ->create();

        // Gestion du tournoi
        Tournament::factory(3)->create();
        $this->tournament = Tournament::find(1);
        $this->tournament->name = 'Tournoi des crêpes';
        $this->tournament->start_date = Carbon::createFromDate('16-10-2024 10:00:00');
        $this->tournament->save();

        $this->tournament = Tournament::find(2);
        $this->tournament->name = 'Vieux tournoi';
        $this->tournament->start_date = Carbon::createFromDate('09-11-2018 11:00:00');
        $this->tournament->save();



        $this->tournament = Tournament::find(3);
        $this->tournament->name = 'Tournoi de doubles';
        $this->tournament->max_users = 24;
        $this->tournament->total_users = 24;
        $this->tournament->start_date = Carbon::createFromDate('06-04-2025 10:00:00');
        $this->tournament->save();

        for ($i=1; $i < 25; $i++){
            $user = User::find($i);
            $this->tournament->users()->attach($user);
        }
    }
}
