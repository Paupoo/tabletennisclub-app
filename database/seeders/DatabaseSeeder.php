<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Services\TournamentTableService;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Domains\Shared\Models\AppSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function __construct(
        private TournamentTableService $tableService,
    ) {}

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AppSetting::set('setup_completed', '1');

        // ── Our club ──────────────────────────────────────────────────────────
        Club::create([
            'name' => 'C.T.T Ottignies-Blocry',
            'licence' => 'BBW214',
            'is_own_club' => true,
            'building_name' => 'Centre Sportif J. Demeester',
            'street' => "Rue de l'Invasion 80",
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'bic' => 'CREGBEBB',
            'bank_account' => 'BE23732333208791',
        ]);

        // ── Opponent clubs (FRBTT/BBW 2025-2026) ─────────────────────────────
        Club::create(['name' => 'ALPA SCHAERBEEK',              'licence' => 'BBW015', 'building_name' => 'Crossing tribune nord',          'street' => 'Rue Ernest Renan 33-35',       'city_code' => '1030', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'SET-JET FLEUR BLEUE',          'licence' => 'BBW034', 'building_name' => 'Complexe Sportif de Jette',       'street' => 'Av. du Comté de Jette 3',      'city_code' => '1090', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'R.E.P. NIVELLOISE',            'licence' => 'BBW118',                                                        'street' => 'Rue des Heures Claires 46',    'city_code' => '1400', 'city_name' => 'Nivelles']);
        Club::create(['name' => 'CTT LIMAL-WAVRE',              'licence' => 'BBW123', 'building_name' => 'Hall des Sports de Limal',         'street' => 'Rue Ch. Jaumotte 156',         'city_code' => '1300', 'city_name' => 'Limal']);
        Club::create(['name' => 'ARC EN CIEL CTT',              'licence' => 'BBW134', 'building_name' => 'Bassin Olympique de Molenbeek',    'street' => 'Rue Van Kalck 93',             'city_code' => '1080', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'MUPPETS T.T. AUDERGHEM',       'licence' => 'BBW145', 'building_name' => "Centre Sportif d'Auderghem",       'street' => 'Chée de Wavre 1690',           'city_code' => '1160', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'CTT ROYAL 1865',               'licence' => 'BBW147', 'building_name' => 'Royal Sport Nautique de Bruxelles', 'street' => 'Chée de Vilvorde 170',         'city_code' => '1120', 'city_name' => 'Bruxelles']);
        Club::create(['name' => "ROYALE PALETTE D'ACIER CLABECQ", 'licence' => 'BBW155', 'building_name' => 'Salle André Menu',                 'street' => 'Allée des Sports 9',           'city_code' => '1480', 'city_name' => 'Tubize']);
        Club::create(['name' => 'LOGIS AUDERGHEM',               'licence' => 'BBW165', 'building_name' => "Centre Sportif d'Auderghem",      'street' => 'Chée de Wavre 1690',           'city_code' => '1160', 'city_name' => 'Bruxelles']);
        Club::create(['name' => "C.T.T. BRAINE L'ALLEUD",        'licence' => 'BBW179',                                                        'street' => 'Rue du Ménil 45',              'city_code' => '1420', 'city_name' => "Braine-l'Alleud"]);
        Club::create(['name' => 'CTT SAFRAN',                    'licence' => 'BBW190', 'building_name' => 'A.S.C.T.R.',                       'street' => 'Av. Du Marathon 1',            'city_code' => '1020', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'CTT LA HULPE RIXENSART',        'licence' => 'BBW194', 'building_name' => 'Hall Omnisport',                   'street' => 'Rue Général de Gaulle 53',     'city_code' => '1310', 'city_name' => 'La Hulpe']);
        Club::create(['name' => 'T.T. ZENITH BRUSSELS',          'licence' => 'BBW205', 'building_name' => 'Centre sportif de St-Gilles',      'street' => 'Rue de Russie 41',             'city_code' => '1060', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'ROYAL CTT MONT ST GUIBERT',     'licence' => 'BBW223',                                                        'street' => 'Rue des Hayeffes 30',          'city_code' => '1435', 'city_name' => 'Mont-St-Guibert']);
        Club::create(['name' => 'CHARLES QUINT TT',              'licence' => 'BBW264', 'building_name' => 'A.S.C.T.R.',                       'street' => 'Av. du Marathon 1',            'city_code' => '1020', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'TT PERWEZ',                     'licence' => 'BBW289', 'building_name' => 'Centre Sportif',                   'street' => 'Rue du Presbytère 5',          'city_code' => '1360', 'city_name' => 'Thorembais-les-Béguines (Perwez)']);
        Club::create(['name' => 'PP WITTERZEE',                  'licence' => 'BBW291', 'building_name' => 'Salle Omnisport',                  'street' => 'Rue René Francq 7',            'city_code' => '1428', 'city_name' => 'Lillois']);
        Club::create(['name' => 'CTT HAMME-MILLE 6V',            'licence' => 'BBW299', 'building_name' => 'Hall Omnisports',                  'street' => 'Chaussée de Wavre 99',         'city_code' => '1390', 'city_name' => 'Grez-Doiceau']);
        Club::create(['name' => 'PALETTE RY TERNEL',             'licence' => 'BBW307', 'building_name' => "Sport'Ittre",                      'street' => 'Rue de Samme 20-22',           'city_code' => '1460', 'city_name' => 'Ittre']);
        Club::create(['name' => 'C.T.T. LE MOULIN',              'licence' => 'BBW315', 'building_name' => 'MJC Le Moulin',                    'street' => 'Rue du Relais 23',             'city_code' => '1370', 'city_name' => 'Zetrud-Lumay']);
        Club::create(['name' => 'PIRANHA TT WATERLOO',           'licence' => 'BBW319', 'building_name' => 'Hall Omnisports',                  'street' => 'Rue T. Delbar 33/6',           'city_code' => '1410', 'city_name' => 'Waterloo']);
        Club::create(['name' => 'EVEIL',                         'licence' => 'BBW321', 'building_name' => "Centre Sportif d'Auderghem",       'street' => 'Chée de Wavre 1690',           'city_code' => '1160', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'SMASH EVERE',                   'licence' => 'BBW323', 'building_name' => "Complexe Sportif d'Evere",         'street' => 'Av. des Anciens Combattants',  'city_code' => '1140', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'GREMLINS 90 FOREST',            'licence' => 'BBW326', 'building_name' => 'Ecole De Puzzel',                  'street' => 'Rue de Fierlant 35',           'city_code' => '1190', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'CTT FONTENYGENAPPE',            'licence' => 'BBW338', 'building_name' => 'Salle Gossiaux',                   'street' => 'Avenue des Combattants 94',    'city_code' => '1470', 'city_name' => 'Bousval']);
        Club::create(['name' => 'AS BEAUCHAMP',                  'licence' => 'BBW345',                                                        'street' => 'Place de la Constellation 3',  'city_code' => '1300', 'city_name' => 'Limal']);
        Club::create(['name' => 'CTT UCCLE PING',                'licence' => 'BBW347', 'building_name' => 'Institut ST Vincent-de-Paul',      'street' => 'Rue Danse 25a',                'city_code' => '1180', 'city_name' => 'Bruxelles']);
        Club::create(['name' => 'CTT PALETTE BLEUE',             'licence' => 'BBW348',                                                        'street' => 'Rue de la Libération 25',      'city_code' => '1440', 'city_name' => 'Braine-le-Château']);
        Club::create(['name' => 'CTT TUBIZE',                    'licence' => 'BBW349', 'building_name' => 'Salle des mérites',                'street' => 'Allée des Sports 9',           'city_code' => '1480', 'city_name' => 'Tubize']);
        Club::create(['name' => 'CTT TOURINNES',                 'licence' => 'BBW350', 'building_name' => 'Centre sportif de Walhain',        'street' => 'Rue Chapelle Ste Anne 14',     'city_code' => '1457', 'city_name' => 'Walhain']);

        Season::factory(10)->create();

        // Drop any stale cached season from a previous seed run so factories
        // resolve Season::current() against the freshly-seeded rows.
        Cache::forget('season.current');

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
            'email' => 'aurelien.paulus@gmail.com',
            'password' => Hash::make('test1234'),
            'first_name' => 'Aurélien',
            'last_name' => 'Paulus',
            'gender' => Gender::MEN->name,
            'phone_number' => '0479577502',
            'birthdate' => '1988-08-17 00:00:00',
            'street' => 'Rue de la chapelle 30',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'ranking' => Ranking::E4->name,
            'licence' => '114399',
            'committee_role' => CommitteeRolesEnum::ADMINISTRATOR,
        ])->club()->associate(Club::own());
        $admin->save();

        // Create test dream team

        $password = Hash::make('password');
        // the players
        $players = [
            ['Olivier', 'Tilmans', Ranking::E6->name, '223344', 'olivier.tilmans@test.com', Gender::MEN->name],
            ['Xavier', 'Coenen', Ranking::E6->name, '123123', 'xavier.coenen@test.com', Gender::MEN->name],
            ['Arnaud', 'Ghysens', Ranking::E2->name, '112233', 'arnaud.ghysens@test.com', Gender::MEN->name],
            ['Éric', 'Godart', Ranking::E0->name, '443211', 'eric.godart@test.com', Gender::MEN->name],
            ['Sébastien', 'Vandevyver', Ranking::E2->name, '987654', 'seba.vande@test.com', Gender::MEN->name],
            ['Dariusz', 'Sekula', Ranking::E2->name, '332211', 'dariusz.sekula@test.com', Gender::MEN->name],
        ];

        foreach ($players as $player) {

            $player = User::make([
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
            ])->club()->associate(Club::own());
            $player->save();
        }

        // Add some random users

        User::make([
            'email' => 'thierry.regnier@test.com',
            'email_verified_at' => now(),
            'password' => $password,
            'first_name' => 'Thierry',
            'last_name' => 'Regnier',
            'gender' => Gender::MEN->name,
            'phone_number' => '047' . fake()->randomNumber(7, true),
            'birthdate' => fake()->dateTimeBetween('-59 years', '-25 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' => Ranking::D6->name,
            'licence' => '154856',
            'has_key' => true,
            'committee_role' => CommitteeRolesEnum::ADMINISTRATOR,
        ])->club()->associate(Club::own())->save();

        User::make([
            'email' => 'manon.patigny@test.com',
            'email_verified_at' => now(),
            'password' => $password,
            'first_name' => 'Manon',
            'last_name' => 'Patigny',
            'gender' => Gender::WOMEN->name,
            'phone_number' => '047' . fake()->randomNumber(7, true),
            'birthdate' => fake()->dateTimeBetween('-59 years', '-25 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' => Ranking::D4->name,
            'licence' => '852364',
            'has_key' => true,
            'committee_role' => CommitteeRolesEnum::SECRETARY,
        ])->club()->associate(Club::first())->save();

        User::make([
            'email' => 'olivier.pauwels@test.com',
            'email_verified_at' => now(),
            'password' => $password,
            'first_name' => 'Olivier',
            'last_name' => 'Pauwels',
            'gender' => Gender::MEN->name,
            'phone_number' => '047' . fake()->randomNumber(7, true),
            'birthdate' => fake()->dateTimeBetween('-59 years', '-25 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' => Ranking::B6->name,
            'licence' => '852398',
            'has_key' => true,
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ])->club()->associate(Club::first())->save();

        User::make([
            'email' => 'gilles.herpigny@test.com',
            'email_verified_at' => now(),
            'password' => $password,
            'first_name' => 'Gilles',
            'last_name' => 'Herpigny',
            'gender' => Gender::MEN->name,
            'phone_number' => '047' . fake()->randomNumber(7, true),
            'birthdate' => fake()->dateTimeBetween('-59 years', '-25 years'),
            'street' => fake()->streetAddress(),
            'city_code' => fake()->postcode(),
            'city_name' => fake()->city(),
            'ranking' => Ranking::D2->name,
            'licence' => '768398',
            'has_key' => true,
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ])->club()->associate(Club::first())->save();

        // Roles — the boolean columns were retired, so roles are assigned here.
        // Mirrors the application: a committee member holds the COMMITTEE base role
        // plus the delegations its statutory title suggests, and the club owner is
        // the administrator. Lazy loading is lifted around this block because
        // Spatie's assignRole() reloads the roles relation as it goes, which the
        // non-production guard would otherwise reject.
        Model::preventLazyLoading(false);

        User::whereNotNull('committee_role')->get()->each(function (User $member): void {
            $member->assignRole(Role::COMMITTEE->value);

            foreach (Role::suggestedFor($member->committee_role) as $delegation) {
                $member->assignRole($delegation->value);
            }
        });

        User::where('email', 'aurelien.paulus@gmail.com')->first()?->assignRole(Role::ADMINISTRATOR->value);

        Model::preventLazyLoading(! app()->isProduction());

        $gilles = User::where('email', 'gilles.herpigny@test.com')->first();
        CashRegister::create([
            'name' => 'Caisse du club',
            'held_by_user_id' => $gilles?->id,
        ]);

        User::factory()->isNotCompetitor()->count(5)->create();

        User::factory()->isCompetitor()->count(2)->create([
            'ranking' => 'NC',
        ]);

        User::factory()->isNotCompetitor()->count(2)->create([
            'ranking' => 'NC',
        ]);

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
                'state' => TableStateEnum::GOOD,
                'room_id' => Room::inRandomOrder()->first()->id,
            ]);
        }

        $rooms = Room::all();
        foreach ($rooms as $room) {
            $this->tableService->updateTablesCount($room);
        }

        User::factory()
            ->isNotCompetitor()
            ->count(100)
            ->create();

        $this->call(SubscriptionSeeder::class);

        // 1-3: teams, divisions, opponents, Interclub fixtures (observer creates empty results)
        $this->call(InterclubScheduleSeeder::class);

        // 4: apply known 2025-2026 scores onto the results (last round 2026-04-17 left empty)
        $this->call(InterclubResultsSeeder::class);

        $this->call(InterclubSeeder::class);

        $this->call(TournamentSeeder::class);

        $this->call(MeetingSeeder::class);

        $this->call(TreasurySeeder::class);

        $this->call(FineSeeder::class);

        $this->call(TrainingPackSeeder::class);

        $this->call(NewsPostSeeder::class);

        $this->call(InterclubSettingsSeeder::class);

        $this->call(EmailTemplateSeeder::class);

        $this->call(DirectedTrainingDemoSeeder::class);

        $this->call(SpamSeeder::class);

        // En dernier : la force list se calcule sur la population définitive,
        // et InterclubSeeder crée encore des compétiteurs.
        RecalculateForceListAction::handle();
    }
}
