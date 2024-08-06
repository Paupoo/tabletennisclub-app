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
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\ForceIndex;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        Club::create([
            'name' => 'C.T.T Ottignies-Blocry',
            'licence' => 'BBW214',
            'street' => 'Rue de l\'invasion 80',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
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
        $team = Team::make([
            'name' => 'Z',
            ])
            ->club()->associate(Club::firstWhere('licence', 'BBW214'))
            ->league()->associate(League::find(1))
            ->season()->associate(Season::find(1));
        $team->save();

        // Create F team team
        $team = Team::make([
            'name' => 'F',
            ])
            ->club()->associate(Club::find(1))
            ->league()->associate(League::firstWhere('division', '4B'))
            ->season()->associate(Season::find(1));
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
            'sex' => Sex::MEN->name,
            'phone_number' => '0479577502',
            'birthday' => '1988-08-17 00:00:00',
            'street' => 'Rue de la chapelle 30',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'ranking' => Ranking::E4->name,
            'licence' => '114399',
        ])->club()->associate(Club::firstWhere('licence', 'BBW214'));
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
                'birthday' => fake()->dateTimeBetween('-59 years', '-25 years'),
                'street' => fake()->streetAddress(),
                'city_code' => fake()->postcode(),
                'city_name' => fake()->city(),
                'ranking' =>  $player[2],
                'licence' => $player[3],
            ])->club()->associate(Club::firstWhere('licence', 'BBW214'));
            $player->save();
            $player->teams()->attach(Team::firstWhere('name', 'Z'));
        }

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
            'birthday' => fake()->dateTimeBetween('-59 years', '-25 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' =>  Ranking::D6->name,
            'licence' => '154856',
        ])->club()->associate(Club::firstWhere('licence', 'BBW214'))->save();

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
            'birthday' => fake()->dateTimeBetween('-59 years', '-25 years'),
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

        // User::factory()->count(40)->create();


        // Set ForceIndexes
        $this->forceIndex->setOrUpdateAll();
    }
}
