<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Collection;
use Livewire\Livewire;

/**
 * Money moving between the club's own accounts is nobody's payment: it has
 * nothing to wait for, so it must never sit in the list of lines to handle.
 */
function itLine(string $date, float $amount, bool $internal): Transaction
{
    return Transaction::create([
        'date' => $date,
        'description' => $internal ? 'VIREMENT VERS EPARGNE' : 'VIREMENT',
        'amount' => $amount,
        'is_internal' => $internal,
    ]);
}

/** @return Collection<int, int> */
function itIdsOnScreen(array $filters): Collection
{
    $screen = Livewire::actingAs(User::factory()->isAdmin()->create())->test('pages::club-admin.treasury.transactions');

    foreach ($filters as $name => $value) {
        $screen->set($name, $value);
    }

    return collect($screen->viewData('transactions')->items())->pluck('id')->sort()->values();
}

it('closes an internal transfer, which never waits for an allocation', function (): void {
    $transfer = itLine('2026-09-10', -500.0, internal: true);

    expect($transfer->isSettled())->toBeTrue();
});

it('files internal transfers under settled, never under unreconciled', function (): void {
    $transfer = itLine('2026-09-10', -500.0, internal: true);
    $untouched = itLine('2026-09-11', 25.0, internal: false);

    expect(itIdsOnScreen(['reconciledFilter' => 'reconciled'])->all())->toBe([$transfer->id])
        ->and(itIdsOnScreen(['reconciledFilter' => 'unreconciled'])->all())->toBe([$untouched->id]);
});

it('keeps an internal transfer outside the date range out of the settled list', function (): void {
    itLine('2026-01-10', -500.0, internal: true);
    $inRange = itLine('2026-09-10', -300.0, internal: true);

    expect(itIdsOnScreen(['reconciledFilter' => 'reconciled', 'dateFrom' => '2026-09-01'])->all())->toBe([$inRange->id]);
});

it('lists internal transfers on their own', function (): void {
    $transfer = itLine('2026-09-10', -500.0, internal: true);
    itLine('2026-09-11', 25.0, internal: false);

    expect(itIdsOnScreen(['reconciledFilter' => 'internal'])->all())->toBe([$transfer->id]);
});
