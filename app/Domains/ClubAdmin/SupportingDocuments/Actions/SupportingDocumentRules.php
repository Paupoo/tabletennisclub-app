<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Actions;

use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use DomainException;

/**
 * What every supporting document must satisfy, on creation as on edit.
 */
final class SupportingDocumentRules
{
    /**
     * @throws DomainException
     */
    public static function assertValid(ExpenseCategory|IncomeCategory $category, float $amount): void
    {
        // Membership fees and trainings are read off the website's payments:
        // a document filed there would count the same euro twice.
        if ($category instanceof IncomeCategory && $category->isSiteOnly()) {
            throw new DomainException(__('This income is accounted for by the website: a supporting document cannot be filed under it.'));
        }

        if ((int) round($amount * 100) <= 0) {
            throw new DomainException(__('The amount of a supporting document must be positive.'));
        }
    }
}
