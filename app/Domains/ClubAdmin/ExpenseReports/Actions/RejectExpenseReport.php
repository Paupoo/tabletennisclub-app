<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Actions;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportRejectedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use DomainException;

final class RejectExpenseReport
{
    /**
     * Turn a report down for good, with the reason sent back to the member.
     *
     * Final: a member who can fix it (a sharper photo, the missing bank proof)
     * resumes it into a new report, and this one stays on record as it was.
     */
    public function __invoke(ExpenseReport $report, User $decider, string $reason): void
    {
        ExpenseReportDecision::ensureDecidable($report, $decider);

        if (blank($reason)) {
            throw new DomainException('A rejection always says why.');
        }

        $report->update([
            'status' => ExpenseReportStatus::Rejected,
            'decision_reason' => trim($reason),
            'decided_by' => $decider->id,
            'decided_at' => now(),
        ]);

        $report->user->notify(new ExpenseReportRejectedNotification($report));
    }
}
