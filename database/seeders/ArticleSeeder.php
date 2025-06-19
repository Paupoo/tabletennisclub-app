<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory(10)->create()->each(function (User $user) {
            // Pour chaque utilisateur, créer 3 articles
            Article::factory(3)->create([
                'user_id' => $user->id,
            ]);
        });
    }
}
