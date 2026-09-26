<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Actions;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use DomainException;

final class WithdrawExpenseReport
{
    /**
     * The member's way out of a report sent by mistake. Never a deletion: the
     * report stays on record, and its proofs follow the purge of the unpaid.
     */
    public function __invoke(ExpenseReport $report): void
    {
        if ($report->status !== ExpenseReportStatus::Submitted) {
            throw new DomainException('Only a report still in progress can be withdrawn.');
        }

        $report->update(['status' => ExpenseReportStatus::Withdrawn]);
    }
}
