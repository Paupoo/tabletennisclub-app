<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Actions;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use DomainException;

final class LinkSupportingDocument
{
    /**
     * Say which bank line or cash movement paid (or brought in) a document.
     *
     * No amount on the link: a debit may pay several invoices and an invoice
     * may be paid in several goes. A total that does not match is a warning on
     * the document, never a refusal.
     *
     * Money the website accounts for is never mixed with money a document
     * justifies: a line already allocated to a member's payment, a cash
     * movement carrying a payable, and the club's internal movements are
     * refused.
     *
     * @throws DomainException
     */
    public function __invoke(SupportingDocument $document, Transaction|CashRegisterEntry $movement): void
    {
        if ($movement instanceof Transaction) {
            $this->assertJustifiable($movement);
            $document->transactions()->syncWithoutDetaching([$movement->id]);

            return;
        }

        if ($movement->payable_type !== null) {
            throw new DomainException(__('This cash movement is a payment the website accounts for: it cannot receive a supporting document.'));
        }

        if ($movement->isInternal()) {
            throw new DomainException(__('This cash movement went to or came from the bank: it is internal and needs no supporting document.'));
        }

        $document->cashRegisterEntries()->syncWithoutDetaching([$movement->id]);
    }

    /**
     * @throws DomainException
     */
    private function assertJustifiable(Transaction $transaction): void
    {
        if ((int) round((float) $transaction->allocated_amount * 100) !== 0 || $transaction->credits()->exists()) {
            throw new DomainException(__('This transaction is allocated to payments of the website: it cannot receive a supporting document.'));
        }

        if ($transaction->is_internal) {
            throw new DomainException(__('This transaction moves money between the club\'s own accounts: it is internal and needs no supporting document.'));
        }
    }
}
