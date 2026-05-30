<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubPosts\Models;

use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Domains\ClubPosts\Models\NewsPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsPost>
 */
class NewsPostFactory extends Factory
{
    protected $model = NewsPost::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'title' => $title,
            'slug' => Str::slug($title), // Génération du slug à partir du titre
            'content' => $this->faker->paragraph(),
            'user_id' => User::factory(),
            'status' => NewsPostStatusEnum::PUBLISHED,
            'category' => 'Partnership', // ou un value de ton enum
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
