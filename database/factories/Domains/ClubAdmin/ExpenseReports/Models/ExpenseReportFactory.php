<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\ExpenseReports\Models;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseReport>
 */
class ExpenseReportFactory extends Factory
{
    protected $model = ExpenseReport::class;

    public function accepted(?float $acceptedAmount = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ExpenseReportStatus::Accepted,
            'accepted_amount' => $acceptedAmount ?? $attributes['amount'],
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'description' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, 3, 150),
            'spent_on' => fake()->dateTimeBetween('-2 months', 'now'),
            'refund_iban' => sprintf('BE%02d%012d', fake()->numberBetween(10, 98), fake()->numberBetween(100000000000, 999999999999)),
            'status' => ExpenseReportStatus::Submitted,
        ];
    }

    public function rejected(string $reason = 'Justificatif illisible.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ExpenseReportStatus::Rejected,
            'decision_reason' => $reason,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ExpenseReportStatus::Withdrawn,
        ]);
    }
}
