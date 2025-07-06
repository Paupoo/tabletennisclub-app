<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ArticlesStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
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
            'status' => ArticlesStatusEnum::PUBLISHED,
            'category' => 'Partnership', // ou un value de ton enum
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
