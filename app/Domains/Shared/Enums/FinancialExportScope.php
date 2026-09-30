<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Which pieces an export of a financial year carries behind the report and
 * its journal, which it always holds.
 */
enum FinancialExportScope: string
{
    case All = 'all';
    case Documents = 'documents';
    case ExpenseReports = 'expense_reports';

    /**
     * The scopes offered: without expense reports, only the documents are.
     *
     * @return list<self>
     */
    public static function offered(): array
    {
        return Feature::ExpenseReports->enabled() ? [self::All, self::Documents, self::ExpenseReports] : [self::Documents];
    }

    public function includesDocuments(): bool
    {
        return $this !== self::ExpenseReports;
    }

    /**
     * Expense reports only while the feature is on: switched off, they are
     * nowhere else in the application either.
     */
    public function includesExpenseReports(): bool
    {
        return $this !== self::Documents && Feature::ExpenseReports->enabled();
    }

    public function label(): string
    {
        return match ($this) {
            self::All => __('Documents and expense reports'),
            self::Documents => __('Supporting documents only'),
            self::ExpenseReports => __('Expense reports only'),
        };
    }
}
