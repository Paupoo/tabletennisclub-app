<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
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

        // Create 1 admin
        User::create([
            'last_name' => 'Paulus',
            'first_name' => 'Aurélien',
            'ranking' => 'E4',
            'licence' => '114399',
            'email' => 'aurelien.paulus@gmail.com',
            'password' => 'test1234',
            'role_id' => 2,
            'is_competitor' => true,
        ]);

        // Create 75 members
        User::factory(96)->create();

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

    }
}
