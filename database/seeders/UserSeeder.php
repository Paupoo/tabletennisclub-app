<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->isNotCompetitor()->count(5)->create();

        User::factory()->isCompetitor()->count(2)->create([
            'ranking' => 'NC',
        ]);

        User::factory()->isNotCompetitor()->count(2)->create([
            'ranking' => 'NC',
        ]);
    }
}
