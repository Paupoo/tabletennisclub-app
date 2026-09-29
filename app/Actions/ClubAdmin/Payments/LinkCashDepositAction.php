<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * « C'est un versement de/vers la caisse » : the till taken to the bank, or a
 * float withdrawn from it.
 *
 * The bank line and the cash movement are the same money seen from two sides.
 * Linked, both become internal — closed, and out of the income and expense
 * flows — the way a transfer to the savings account is.
 */
final class LinkCashDepositAction
{
    /**
     * @throws DomainException when the two sides cannot be the same money
     */
    public function __invoke(Transaction $transaction, CashRegisterEntry $entry): void
    {
        $this->assertSameMoney($transaction, $entry);
        $this->assertFreeBankLine($transaction);
        $this->assertFreeCashMovement($entry);

        DB::transaction(function () use ($transaction, $entry): void {
            $entry->forceFill(['transaction_id' => $transaction->id])->save();
            $transaction->update(['is_internal' => true]);
        });
    }

    /**
     * @throws DomainException
     */
    private function assertFreeBankLine(Transaction $transaction): void
    {
        if ($transaction->is_internal) {
            throw new DomainException(__('This transaction is already an internal movement.'));
        }

        if ((int) round((float) $transaction->allocated_amount * 100) !== 0 || $transaction->supportingDocuments()->exists()) {
            throw new DomainException(__('This transaction is already allocated or justified: it cannot be a movement of the till.'));
        }
    }

    /**
     * @throws DomainException
     */
    private function assertFreeCashMovement(CashRegisterEntry $entry): void
    {
        if ($entry->payable_type !== null || $entry->isInternal() || $entry->supportingDocuments()->exists()) {
            throw new DomainException(__('This cash movement is already a payment, a justified movement or a bank deposit.'));
        }
    }

    /**
     * Money leaving the till enters the bank, and the other way round, to the
     * cent.
     *
     * @throws DomainException
     */
    private function assertSameMoney(Transaction $transaction, CashRegisterEntry $entry): void
    {
        $bankCents = (int) round((float) $transaction->amount * 100);

        if ($bankCents + $entry->amount !== 0 || $bankCents === 0) {
            throw new DomainException(__('A deposit takes out of the till exactly what enters the bank, or the other way round.'));
        }
    }
}
