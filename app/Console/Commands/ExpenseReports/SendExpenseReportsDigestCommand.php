<?php

declare(strict_types=1);

namespace App\Console\Commands\ExpenseReports;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsDigestNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Enums\Permission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The weekly digest of expense reports waiting for a decision.
 *
 * Every report still submitted, not only this week's: one left undecided
 * would otherwise drop out of the next mail and sleep. Each decider gets the
 * list without their own reports, which they may not decide on — and nothing
 * at all when that leaves the list empty.
 */
#[Signature('expense-reports:send-digest')]
#[Description('Mail each decider the expense reports still waiting for a decision.')]
class SendExpenseReportsDigestCommand extends Command
{
    public function handle(): int
    {
        $waiting = ExpenseReport::with('user')
            ->where('status', ExpenseReportStatus::Submitted)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $sent = 0;

        foreach (User::permission(Permission::ExpenseReportsProcess->value)->get() as $decider) {
            $theirs = $waiting->reject(fn (ExpenseReport $report): bool => $report->user_id === $decider->id)->values();

            if ($theirs->isEmpty()) {
                continue;
            }

            $decider->notify(new ExpenseReportsDigestNotification($theirs));
            $sent++;
        }

        $this->components->info(trans_choice('{0}No digest to send.|[1,*]Digest sent to :count decider(s).', $sent, ['count' => $sent]));

        return self::SUCCESS;
    }
}
