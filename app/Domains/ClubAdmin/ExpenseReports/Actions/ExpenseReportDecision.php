<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Actions;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use DomainException;

/**
 * The two rules every decision on a report shares.
 */
final class ExpenseReportDecision
{
    public static function ensureDecidable(ExpenseReport $report, User $decider): void
    {
        if ($report->user_id === $decider->id) {
            throw new DomainException('Nobody decides on their own expense report.');
        }

        if ($report->status !== ExpenseReportStatus::Submitted) {
            throw new DomainException('Only a submitted expense report can be decided on.');
        }
    }
}
