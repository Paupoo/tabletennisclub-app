<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\Ranking;
use App\Enums\Gender;
use App\Models\Club;
use App\Models\League;
use App\Models\Room;
use App\Models\Season;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\ForceList;
use App\Services\TournamentMatchService;
use App\Services\TournamentPoolService;
use App\Services\TournamentTableService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private Tournament $tournament;

    public function __construct(
        private ForceList $forceList,
        private TournamentTableService $tableService,
        private TournamentPoolService $poolService,
        private TournamentMatchService $matchService,
    ) {}

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

        Season::factory(11)->create();

        League::create([
            'division' => '5E',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::MEN->value,
            'season_id' => 1,

        ]);
        League::create([
            'division' => '5E',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::MEN->value,
            'season_id' => 2,

        ]);

        League::create([
            'division' => '4B',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::MEN->value,
            'season_id' => 3,

        ]);

        League::create([
            'division' => '3F',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::VETERANS->value,
            'season_id' => 4,

        ]);

        League::create([
            'division' => '4B',
            'level' => LeagueLevel::PROVINCIAL_BW->value,
            'category' => LeagueCategory::WOMEN->value,
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
            'gender' => Gender::MEN->value,
            'phone_number' => '0479577502',
            'birthdate' => '1988-08-17 00:00:00',
            'street' => 'Rue de la chapelle 30',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'ranking' => Ranking::E2->name,
            'licence' => '114399',
        ])->club()->associate(Club::firstWhere('licence', config('app.club_licence')));
        $admin->save();
        $admin->teams()->attach(Team::firstWhere('name', 'Z'));

        // Create test dream team

        $password = Hash::make('password');
        // the players
        $players = [
            ['AUGUSTIN', 'DOCQUIER', 'C0', '101683', 'augustin.docquier@test.com', Gender::MEN->value],
            ['BENOIT', 'KUNSCH', 'C0', '100696', 'benoit.kunsch@test.com', Gender::MEN->value],
            ['OLIVIER', 'PAUWELS', 'C0', '138942', 'olivier.pauwels@test.com', Gender::MEN->value],
            ['MATHIEU', 'RENIERS', 'C0', '143164', 'mathieu.reniers@test.com', Gender::MEN->value],
            ['SAMUEL', 'ALEXANDRE-MARTIN', 'C2', '166036', 'samuel.alexandre-martin@test.com', Gender::MEN->value],
            ['PIERRE OLIVIER', 'BERTRAND', 'C4', '132472', 'pierre-olivier.bertrand@test.com', Gender::MEN->value],
            ['JEAN', 'DOCQUIER', 'C4', '115989', 'jean.docquier@test.com', Gender::MEN->value],
            ['SIMON', 'IZZARD', 'C6', '100837', 'simon.izzard@test.com', Gender::MEN->value],
            ['ERIC', 'FILEE', 'D0', '101675', 'eric.filee@test.com', Gender::MEN->value],
            ['PHILIPPE', 'JACQUERYE', 'D0', '106329', 'philippe.jacquerye@test.com', Gender::MEN->value],
            ['PIERRE', 'NYST', 'D0', '102487', 'pierre.nyst@test.com', Gender::MEN->value],
            ['GILLES', 'HERPIGNY', 'D2', '103647', 'gilles.herpigny@test.com', Gender::MEN->value],
            ['MANON', 'PATINY', 'D2', '103867', 'manon.patiny@test.com', Gender::WOMEN->value],
            ['JEAN LOUIS', 'WAROQUET', 'D4', '102396', 'jean-louis.waroquet@test.com', Gender::MEN->value],
            ['ERIC', 'GODARD', 'D6', '171075', 'eric.godard@test.com', Gender::MEN->value],
            ['DARIUSZ', 'SEKULA', 'D6', '164838', 'dariusz.sekula@test.com', Gender::MEN->value],
            ['MICHEL', 'DARDENNE', 'E0', '104175', 'michel.dardenne@test.com', Gender::MEN->value],
            ['VINCENT', 'MARLAIR', 'E0', '109273', 'vincent.marlair@test.com', Gender::MEN->value],
            ['THIERRY', 'RENIERS', 'E0', '149689', 'thierry.reniers@test.com', Gender::MEN->value],
            ['ARNAUD', 'GHYSENS', 'E2', '107028', 'arnaud.ghysens@test.com', Gender::MEN->value],
            ['JEAN-PIERRE', 'VAN OUDENHOVE', 'E2', '173945', 'jean-pierre.vanoudenhove@test.com', Gender::MEN->value],
            ['SEBASTIEN', 'VANDEVYVER', 'E2', '149043', 'sebastien.vandevyver@test.com', Gender::MEN->value],
            ['XAVIER', 'COENEN', 'E4', '168706', 'xavier.coenen@test.com', Gender::MEN->value],
            ['AUDREY', 'DE SCHRIJVER', 'E4', '133097', 'audrey.de-schrijver@test.com', Gender::WOMEN->value],
            ['PIERRE', 'LAFLEUR', 'E6', '172352', 'pierre.lafleur@test.com', Gender::MEN->value],
            ['HUGO', 'VAN OUDENHOVE', 'E6', '173944', 'hugo.vanoudenhove@test.com', Gender::MEN->value],
            ['KARL', 'VANDERHULST', 'E6', '107092', 'karl.vanderhulst@test.com', Gender::MEN->value],
            ['JULIEN', 'VERKAEREN', 'E6', '159558', 'julien.verkaeren@test.com', Gender::MEN->value],
            ['DIEGO', 'DESWYSEN', 'NC', '174724', 'diego.deswysen@test.com', Gender::MEN->value],
            ['FELIX', 'FINK', 'NC', '171974', 'felix.fink@test.com', Gender::MEN->value],
            ['ARTHUR', 'JANSSEN', 'NC', '174337', 'arthur.janssen@test.com', Gender::MEN->value],
            ['HECTOR', 'LOIX', 'NC', '172446', 'hector.loix@test.com', Gender::MEN->value],
            ['EMILIEN', 'VANDERBIST', 'NC', '176388', 'emilien.vanderbist@test.com', Gender::MEN->value],
        ];

        foreach ($players as $player) {

            $player = User::make([
                'is_active' => true,
                'is_admin' => false,
                'is_competitor' => true,
                'email' => $player[4],
                'email_verified_at' => now(),
                'password' => $password,
                'remember_token' => Str::random(10),
                'first_name' => $player[0],
                'last_name' => $player[1],
                'gender' => $player[5],
                'phone_number' => '047' . fake()->randomNumber(7, true),
                'birthdate' => fake()->dateTimeBetween('-59 years', '-25 years'),
                'street' => fake()->streetAddress(),
                'city_code' => fake()->postcode(),
                'city_name' => fake()->city(),
                'ranking' => $player[2],
                'licence' => $player[3],
            ])->club()->associate(Club::firstWhere('licence', config('app.club_licence')));
            $player->save();
            $player->teams()->attach(Team::firstWhere('name', 'Z'));

            // Promote Oliver captain of team Z
            if ($player->licence === '173945') {
                $teamZ->update([
                    'captain_id' => $player->id,
                ]);
            }
        }

        // Promote some committee members

        $committeeMembers = [
            [
                'first_name' => 'Thierry',
                'last_name' => 'Regnier',
            ],
            [
                'first_name' => 'Manon',
                'last_name' => 'Patiny',
            ],
            [
                'first_name' => 'Olivier',
                'last_name' => 'Pauwels',
            ],
            [
                'first_name' => 'Gilles',
                'last_name' => 'Herpigny',
            ],
            [
                'first_name' => 'Éric',
                'last_name' => 'Godart',
            ],
            [
                'first_name' => 'Jean-Pierre',
                'last_name' => 'Van Oudenhove',
            ],
        ];

        foreach($committeeMembers as $member){

            User::where('first_name', $member['first_name'])
            ->where('last_name', $member['last_name'])
            ->update([
                'is_committee_member' => true,
            ]);
        }

        User::factory()->isNotCompetitor()->count(5)->create();

        User::factory()->isCompetitor()->count(2)->create([
            'ranking' => 'NC',
        ]);

        User::factory()->isNotCompetitor()->count(2)->create([
            'ranking' => 'NC',
        ]);

        // Créer 25 users non compétiteurs
        User::factory()
            ->isNotCompetitor()
            ->count(25)
            ->create();

        // Créer 25 compétiteurs
        // User::factory()
        //     ->isCompetitor()
        //     ->count(25)
        //     ->create();

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

        for ($i = 0; $i < 15; $i++) {
            Table::create([
                'name' => $i + 1,
                'purchased_on' => fake()->dateTimeBetween('-10 years', '-1 year'),
                'state' => 'used',
                'room_id' => Room::inRandomOrder()->first()->id,
            ]);
        }

        $rooms = Room::all();
        foreach ($rooms as $room) {
            $this->tableService->updateTablesCount($room);
        }

        // Gestion du tournoi
        Tournament::factory(3)->create();
        $this->tournament = Tournament::find(1);
        $this->tournament->name = 'Tournoi des crêpes';

        $this->tournament->save();

        $this->tournament = Tournament::find(2);
        $this->tournament->name = 'Petit tournoi amical';
        $this->tournament->total_users = 16;
        $this->tournament->max_users = 16;
        $this->tournament->has_handicap_points = true;
        $this->tournament->save();
        $this->tournament->rooms()->sync([1, 2]);

        // Link tables
        $this->tableService->linkAvailableTables($this->tournament);

        // Add users
        for ($i = 1; $i < 17; $i++) {
            $user = User::find($i);
            $this->tournament->users()->attach($user);
        }

        // Generate pools
        $this->poolService->distributePlayersInPools($this->tournament, 4);

        // Generate matches
        foreach ($this->tournament->pools as $pool) {
            $this->matchService->generateMatches($pool);
        }

        $this->tournament = Tournament::find(3);
        $this->tournament->name = 'Tournoi de doubles';
        $this->tournament->max_users = 50;
        $this->tournament->total_users = 50;
        $this->tournament->has_handicap_points = true;
        $this->tournament->save();
        $this->tournament->rooms()->sync([1, 2]);

        // Link tables
        $this->tableService->linkAvailableTables($this->tournament);

        // Add users

        for ($i = 1; $i < 50; $i++) {
            $user = User::find($i);
            $this->tournament->users()->attach($user);
        }

        // Generate pools
        $this->poolService->distributePlayersInPools($this->tournament, 10);

        // Generate matches
        foreach ($this->tournament->pools as $pool) {
            $this->matchService->generateMatches($pool);
        }
    }
}
