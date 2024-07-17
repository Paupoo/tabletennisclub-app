<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;
use App\Http\Controllers\UserController;
use App\Models\Competition;
use App\Models\Role;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Services\ForceIndex;
use Illuminate\Support\Facades\DB;

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
        // Create roles
        Role::create([
            'name' => 'Member',
            'description' => 'Members can subscribe to trainings, matches and events. They also see contact info of other member and invite guests to join the club.'
        ]);

        Role::create([
            'name' => 'Admin',
            'description' => 'Admins are building and maintaining the website. They have access to all the features of the applications to manage roles for example.'
        ]);

        Role::create([
            'name' => 'Committee Member',
            'description' => 'Committe Members have various administrative privileges, such as team, rooms, trainings, match and members management.'
        ]);

        // Create "no team"
        Team::create([
            'name' => 'Pool',
            'season' => '',
            'division' => '',
        ]);

        // Create Z team team
        Team::create([
            'name' => 'Z',
            'season' => '2024-2025',
            'division' => 'P5C'
        ]);

        // Create some matches for Z team
        $competitions = Competition::factory(15)->create();

        // Create 1 admin
        $admin = User::create([
            'first_name' => 'Aurélien',
            'last_name' => 'Paulus',
            'ranking' => 'E4',
            'licence' => '114399',
            'email' => 'aurelien.paulus@gmail.com',
            'password' => 'test1234',
            'role_id' => 2,
            'is_competitor' => true,
            'is_active' => true,
            'team_id' => 2,
        ]);

        foreach($competitions as $competition) {
            $admin->competitions()->attach($competition->id, [
                'is_subscribed' => false,
                'is_selected' => false,
                'has_played' => false,
            ]);
        }
        
        
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
            $user = User::create([
                'first_name' => $player[0],
                'last_name' => $player[1],
                'ranking' => $player[2],
                'licence' => $player[3],
                'email' => $player[4],
                'password' => 'password',
                'role_id' => 1,
                'is_competitor' => true,
                'is_active' => true,
                'team_id' => 2,
            ]);
        // the matches
        foreach($competitions as $competition) {
            $user->competitions()->attach($competition->id, [
                'is_subscribed' => false,
                'is_selected' => false,
                'has_played' => false,
            ]);
        }

        }

        // Create 75 members
        User::factory(75)->create();

        // If a player is competitor and has no ranking, get one.
        $affected = DB::table('users')
        ->where('ranking', '=', null)
        ->where('is_competitor',
            '=',
            true
        )
        ->update(['ranking' => 'NC']);

        // Create the rooms
        Room::create([
            'name' => 'Demeester /-1',
            'street' => 'Rue de l\'Invasion 80',
            'city_code' => '1340',
            'city_name'=> 'Ottignies-Louvain-la-Neuve',
            'building_name' => 'Centre Sportif Jean Demeester',
            'access_description'=> 'Salle au -1',
            'capacity_trainings' => 12,
            'capacity_matches' => 2,
        ]);

        Room::create([
            'name' => 'Demeester /0',
            'street' => 'Rue de l\'Invasion 80',
            'city_code' => '1340',
            'city_name'=> 'Ottignies-Louvain-la-Neuve',
            'building_name' => 'Centre Sportif Jean Demeester',
            'access_description'=> 'Salle au RDC',
            'capacity_trainings' => 8,
            'capacity_matches' => 1,
        ]);

        Room::create([
            'name' => 'Blocry G3',
            'street' => 'Place des sports, 1',
            'city_code' => '1340',
            'city_name'=> 'Ottignies-Louvain-la-Neuve',
            'building_name' => 'Complexe Sportif de Blocry',
            'access_description'=> 'Salle G3',
            'capacity_trainings' => 16,
            'capacity_matches' => 0,
        ]);

        // Set ForceIndexes
        $this->forceIndex->set();
    }
}
