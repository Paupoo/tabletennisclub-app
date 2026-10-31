<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegisterCount>
 */
class CashRegisterCountFactory extends Factory
{
    protected $model = CashRegisterCount::class;

    /**
     * A count that matched the books.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_id' => fn (): int => CashRegister::club()->id,
            'expected' => 10_000,
            'counted' => 10_000,
            'counted_by_id' => null,
            'notes' => null,
        ];
    }

    /**
     * A count that found the drawer short of `$cents`.
     */
    public function short(int $cents): static
    {
        return $this->state(fn (array $attributes): array => ['counted' => $attributes['expected'] - $cents]);
    }
}
