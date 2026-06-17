<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Contact\Models\Spam;
use Illuminate\Database\Seeder;

class SpamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Spam::factory(10)->blocked()->create();
    }
}
