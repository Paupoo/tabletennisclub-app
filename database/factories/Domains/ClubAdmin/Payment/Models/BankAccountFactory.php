<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\Shared\Enums\BankAccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iban' => 'BE' . $this->faker->unique()->numerify('##############'),
            'name' => __('Current account'),
            'type' => BankAccountType::Current,
        ];
    }

    public function savings(): static
    {
        return $this->state(fn (): array => [
            'name' => __('Savings account'),
            'type' => BankAccountType::Savings,
        ]);
    }
}
