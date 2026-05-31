<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Competitions\Tournament\Models;

use App\Domains\Competitions\Tournament\Models\Pool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pool>
 */
class PoolFactory extends Factory
{
    protected $model = Pool::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }
}
