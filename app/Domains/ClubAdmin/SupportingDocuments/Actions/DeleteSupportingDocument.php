<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Actions;

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use DomainException;

final class DeleteSupportingDocument
{
    /**
     * Put a document in the bin — only once it justifies nothing: a linked
     * document is the proof of a movement, and deleting it would reopen that
     * movement behind the treasurer's back. Soft-deleted, files kept.
     *
     * @throws DomainException
     */
    public function __invoke(SupportingDocument $document): void
    {
        if ($document->isSettled()) {
            throw new DomainException(__('Unlink this document from its movements before deleting it.'));
        }

        $document->delete();
    }
}
