<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Actions;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;

final class UnlinkSupportingDocument
{
    /**
     * Undo a link. A bank line left without any document goes back to the
     * lines to handle, and a document left without any movement is to settle
     * again.
     */
    public function __invoke(SupportingDocument $document, Transaction|CashRegisterEntry $movement): void
    {
        if ($movement instanceof Transaction) {
            $document->transactions()->detach($movement->id);
            LinkSupportingDocument::audit($document, $movement, 'supporting_document_unlinked');

            return;
        }

        $document->cashRegisterEntries()->detach($movement->id);
        LinkSupportingDocument::audit($document, $movement, 'supporting_document_unlinked');
    }
}
