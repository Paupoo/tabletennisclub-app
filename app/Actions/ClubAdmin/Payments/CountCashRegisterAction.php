<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterCount;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use Illuminate\Support\Facades\DB;

/**
 * « Contrôler la caisse » : the treasurer counted the drawer, the app compares
 * it with the books and files the difference itself.
 *
 * - A surplus is other income, whole.
 * - A shortfall is blamed on the cash that came in since the previous count,
 *   spread over its income categories pro rata of the amounts: a count made
 *   right before a tournament leaves the next shortfall to the tournament.
 * - A shortfall with no cash income in the period is an other expense.
 *
 * One movement per poste, so the till's balance becomes what was counted and
 * the report files each part where it belongs. A count that matched writes no
 * movement but is kept: it bounds the next period.
 */
final class CountCashRegisterAction
{
    public function __invoke(CashRegister $till, int $countedCents, User $countedBy, ?string $notes = null): CashRegisterCount
    {
        return DB::transaction(function () use ($till, $countedCents, $countedBy, $notes): CashRegisterCount {
            $till = CashRegister::query()->lockForUpdate()->findOrFail($till->id);
            $expected = $till->currentBalance();
            $difference = $countedCents - $expected;

            foreach ($this->spread($till, $difference) as $category => $cents) {
                CashRegisterEntry::create([
                    'cash_register_id' => $till->id,
                    'amount' => $cents,
                    'reason' => CashRegisterEntry::DIFFERENCE_REASON,
                    'category' => $category,
                    'recorded_by_id' => $countedBy->id,
                    'notes' => $notes,
                ]);
            }

            return CashRegisterCount::create([
                'cash_register_id' => $till->id,
                'expected' => $expected,
                'counted' => $countedCents,
                'counted_by_id' => $countedBy->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * The cash that came in since the previous count, by income category.
     *
     * @return array<string, int>
     */
    private function incomeSinceLastCount(CashRegister $till): array
    {
        $since = CashRegisterCount::query()
            ->where('cash_register_id', $till->id)
            ->latest('created_at')
            ->latest('id')
            ->value('created_at');

        $income = [];

        $till->entries()
            ->where('amount', '>', 0)
            ->when($since !== null, fn ($query) => $query->where('created_at', '>', $since))
            ->with(['supportingDocuments', 'payable'])
            ->get()
            ->each(function (CashRegisterEntry $entry) use (&$income): void {
                foreach ($entry->incomeShares() as $category => $cents) {
                    $income[$category] = ($income[$category] ?? 0) + $cents;
                }
            });

        return $income;
    }

    /**
     * Split `$amount` pro rata of `$weights`, to the cent, the parts adding up
     * exactly: what rounding leaves goes to the largest remainders.
     *
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    private function proRata(int $amount, array $weights, int $total): array
    {
        $sign = $amount < 0 ? -1 : 1;
        $absolute = abs($amount);
        $parts = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $exact = $absolute * $weight / $total;
            $parts[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $parts[$key];
        }

        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, $absolute - array_sum($parts)) as $key) {
            $parts[$key]++;
        }

        return array_map(fn (int $part): int => $sign * $part, array_filter($parts));
    }

    /**
     * The difference, poste by poste, in cents.
     *
     * @return array<string, int> keyed by category value
     */
    private function spread(CashRegister $till, int $difference): array
    {
        if ($difference === 0) {
            return [];
        }

        if ($difference > 0) {
            return [IncomeCategory::Other->value => $difference];
        }

        $income = $this->incomeSinceLastCount($till);
        $total = array_sum($income);

        if ($total === 0) {
            return [ExpenseCategory::Other->value => $difference];
        }

        return $this->proRata($difference, $income, $total);
    }
}
