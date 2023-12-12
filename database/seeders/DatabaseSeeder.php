<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create 1 admin
        User::factory(1)->create([
            'last_name' => 'admin',
            'first_name' => 'admin',
            'email' => 'aurelien.paulus@gmail.com',
            'role' => 'admin',
        ]);

        // Create 35 members
        User::factory(35)->create();


        // Create 4 comittee members
        User::factory(4)->create([
            'role' => 'comittee',
        ]);
    }
}
