<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Undo {@see LinkCashDepositAction}: the bank line goes back to the lines to
 * handle, and the cash movement is an ordinary one again.
 */
final class UnlinkCashDepositAction
{
    public function __invoke(CashRegisterEntry $entry): void
    {
        $transaction = $entry->transaction_id === null ? null : Transaction::find($entry->transaction_id);

        DB::transaction(function () use ($entry, $transaction): void {
            $entry->forceFill(['transaction_id' => null])->save();
            $transaction?->update(['is_internal' => false]);

            if ($transaction instanceof Transaction) {
                LinkCashDepositAction::audit($entry, $transaction->id, 'cash_deposit_unlinked');
            }
        });
    }
}
