<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use DomainException;

/**
 * « C'est le solde d'ouverture » : what the drawer already held before the
 * app followed it. Neither income nor a transfer from the bank — it is
 * listed apart, out of the flows.
 *
 * Only the first movement of a till, money that came in and that nothing
 * explains yet: anywhere else, the same gesture would take a surplus out of
 * the income.
 */
final class DeclareOpeningBalanceAction
{
    /**
     * @throws DomainException when the movement cannot be the till's opening balance
     */
    public function __invoke(CashRegisterEntry $entry): void
    {
        if ($entry->amount <= 0) {
            throw new DomainException(__('An opening balance is money the till held: it comes in.'));
        }

        if ($entry->isClosed()) {
            throw new DomainException(__('This movement is already explained.'));
        }

        if (! $entry->isFirstOfItsTill()) {
            throw new DomainException(__('Only the first movement of the till can be its opening balance.'));
        }

        $entry->update(['reason' => CashRegisterEntry::OPENING_REASON]);
    }

    /**
     * Back among the movements to process.
     */
    public function undo(CashRegisterEntry $entry): void
    {
        if (! $entry->isOpeningBalance()) {
            return;
        }

        $entry->update(['reason' => 'manual']);
    }
}
