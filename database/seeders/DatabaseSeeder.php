<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;    

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // Create roles
        Role::factory()->create([
            'name' => 'Member',
            'description' => 'Members can subscribe to trainings, matches and events. They also see contact info of other member and invite guests to join the club.'
        ]);

        Role::factory()->create([
            'name' => 'Admin',
            'description' => 'Admins are building and maintaining the website. They have access to all the features of the applications to manage roles for example.'
        ]);

        Role::factory()->create([
            'name' => 'Committee Member',
            'description' => 'Committe Members have various administrative privileges, such as team, rooms, trainings, match and members management.'
        ]);

        // Create 1 admin
        User::factory(1)->create([
            'last_name' => 'Paulus',
            'first_name' => 'Aurélien',
            'ranking' => 'E4',
            'licence' => '114399',
            'email' => 'aurelien.paulus@gmail.com',
            'password' => 'test1234',
            'role_id' => 2,
        ]);

        // Create 75 members
        User::factory(75)->create();

    }
}
