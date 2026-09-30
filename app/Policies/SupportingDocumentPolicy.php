<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\Permission;

/**
 * Who reads, files and links supporting documents.
 *
 * Reading follows the bank lines ({@see Permission::TransactionsView}): the
 * committee and the accounts auditors see every document and its files.
 * Filing and linking is the treasury's ({@see Permission::SupportingDocumentsManage}).
 *
 * The cash register délégation keeps its own till: it may file the ticket of
 * something paid from the till and link it to a cash movement — never to a
 * bank line, and never a document that already justifies one, which is the
 * treasury's to touch.
 */
class SupportingDocumentPolicy
{
    public function create(User $user): bool
    {
        return $this->managesDocuments($user) || $this->keepsTheTill($user);
    }

    /** Delete, soft: the action refuses a document that still justifies a movement. */
    public function delete(User $user, SupportingDocument $document): bool
    {
        return $this->update($user, $document);
    }

    /** Take the till to the bank, or a float out of it: both sides are touched. */
    public function linkCashDeposit(User $user): bool
    {
        return Feature::CashRegister->enabled() && $this->managesDocuments($user);
    }

    public function linkCashRegisterEntry(User $user, SupportingDocument $document): bool
    {
        return Feature::CashRegister->enabled() && $this->update($user, $document);
    }

    public function linkTransaction(User $user): bool
    {
        return $this->managesDocuments($user);
    }

    public function update(User $user, SupportingDocument $document): bool
    {
        return $this->managesDocuments($user)
            || ($this->keepsTheTill($user) && $document->transactions()->doesntExist());
    }

    public function view(User $user, SupportingDocument $document): bool
    {
        return $this->viewAny($user) && ($this->readsTheBooks($user) || $document->transactions()->doesntExist());
    }

    public function viewAny(User $user): bool
    {
        return $this->readsTheBooks($user) || $this->keepsTheTill($user);
    }

    private function keepsTheTill(User $user): bool
    {
        return Feature::CashRegister->enabled() && $user->can(Permission::CashRegisterEntryCreate->value);
    }

    private function managesDocuments(User $user): bool
    {
        return $user->can(Permission::SupportingDocumentsManage->value);
    }

    private function readsTheBooks(User $user): bool
    {
        return $user->can(Permission::TransactionsView->value) || $this->managesDocuments($user);
    }
}
