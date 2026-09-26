<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Actions;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportSubmittedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Enums\Permission;
use App\Support\AccountProxy;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class SubmitExpenseReport
{
    /**
     * Record money a member advanced for the club, with its proofs.
     *
     * Only an adult, acting for themself, may: a minor is never answerable for
     * the club's money, and a guardian holding a ward's seat declares from their
     * own account. The screen already hides the form; this is the last word.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function __invoke(
        User $author,
        ExpenseCategory $category,
        string $description,
        float $amount,
        CarbonInterface $spentOn,
        string $refundIban,
        array $files,
        ?ExpenseReport $resumedFrom = null,
    ): ExpenseReport {
        if (! $author->isAdult() || AccountProxy::isActing()) {
            throw new DomainException('Only an adult member, acting for themself, may submit an expense report.');
        }

        if ($files === []) {
            throw new DomainException('An expense report needs at least one proof.');
        }

        $report = DB::transaction(fn (): ExpenseReport => ExpenseReport::create([
            'user_id' => $author->id,
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
            'spent_on' => $spentOn,
            'refund_iban' => $refundIban,
            'status' => ExpenseReportStatus::Submitted,
            'resumed_from_id' => $resumedFrom?->id,
        ]));

        (new StoreExpenseReportFiles)($report, $files);

        $report->setRelation('user', $author);

        Notification::send(
            User::permission(Permission::ExpenseReportsProcess->value)->whereKeyNot($author->id)->get(),
            new ExpenseReportSubmittedNotification($report),
        );

        return $report->load('files');
    }
}
