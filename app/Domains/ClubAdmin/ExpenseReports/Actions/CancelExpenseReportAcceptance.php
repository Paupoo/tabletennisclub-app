<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Actions;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelExpenseReportAcceptance
{
    /**
     * Undo an acceptance while no money has left: the refund is cancelled and
     * the report waits for a decision again.
     *
     * The only way back from "accepted". The payments screen refuses to cancel
     * these refunds itself, so the report and its refund cannot drift apart.
     * Once the debit is reconciled, nothing here moves any more — a mistake by
     * then is settled outside the application.
     */
    public function __invoke(ExpenseReport $report, User $decider): void
    {
        if ($report->user_id === $decider->id) {
            throw new DomainException('Nobody decides on their own expense report.');
        }

        if ($report->status !== ExpenseReportStatus::Accepted) {
            throw new DomainException('Only an accepted report can have its acceptance undone.');
        }

        $refund = $report->refund;

        if ($refund !== null && ((float) $refund->amount_paid > 0.0 || $refund->status !== 'to_refund')) {
            throw new DomainException('Money has already left for this report: its acceptance can no longer be undone.');
        }

        DB::transaction(function () use ($report, $refund): void {
            $refund?->update(['status' => 'cancelled']);

            $report->update([
                'status' => ExpenseReportStatus::Submitted,
                'accepted_amount' => null,
                'decision_reason' => null,
                'decided_by' => null,
                'decided_at' => null,
            ]);
        });
    }
}
