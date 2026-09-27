<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Actions;

use App\Actions\ClubAdmin\Payments\OpenRefundAction;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportAcceptedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AcceptExpenseReport
{
    /**
     * Agree to pay a member back, and open the refund the treasurer will wire.
     *
     * The amount may come down — a personal item on the same receipt — but
     * never up: a typo on the decider's side must not cost the club. Coming
     * down always says why, since the member reads it.
     *
     * Nobody decides on their own report: that is the one rule an auditor
     * checks first.
     */
    public function __invoke(ExpenseReport $report, User $decider, ?float $acceptedAmount = null, ?string $reason = null): void
    {
        $acceptedAmount ??= $report->amount;
        $reason = filled($reason) ? trim($reason) : null;

        ExpenseReportDecision::ensureDecidable($report, $decider);

        if ($acceptedAmount <= 0.0) {
            throw new DomainException('An accepted amount must be above zero.');
        }

        if (round($acceptedAmount, 2) > $report->amount) {
            throw new DomainException('An expense report is never accepted above the declared amount.');
        }

        if (round($acceptedAmount, 2) < $report->amount && $reason === null) {
            throw new DomainException('Accepting less than declared needs a reason the member can read.');
        }

        DB::transaction(function () use ($report, $decider, $acceptedAmount, $reason): void {
            $report->update([
                'status' => ExpenseReportStatus::Accepted,
                'accepted_amount' => $acceptedAmount,
                'decision_reason' => $reason,
                'decided_by' => $decider->id,
                'decided_at' => now(),
            ]);

            (new OpenRefundAction)->forPayable($report, $acceptedAmount, $report->refund_iban);
        });

        $report->user->notify(new ExpenseReportAcceptedNotification($report));
    }
}
