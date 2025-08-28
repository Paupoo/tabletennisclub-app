<?php

namespace Database\Factories;

use App\Models\Spam;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpamFactory extends Factory
{
    protected $model = Spam::class;

    public function definition(): array
    {
        return [
            'ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'inputs' => [
                'email' => $this->faker->email(),
                'name' => $this->faker->name(),
                'message' => $this->faker->paragraph(),
            ],
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => now(),
        ];
    }
}