<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A new report to decide on — in the bell only. A mail per four-euro receipt
 * would soon be noise; the Sunday digest carries what is left waiting.
 */
class ExpenseReportSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public ExpenseReport $report) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('New expense report to decide'),
            'body' => __(':name — :amount €', [
                'name' => $this->report->user->full_name,
                'amount' => number_format($this->report->amount, 2, ',', ' '),
            ]),
            'url' => route('admin.treasury.expense-reports', ['report' => $this->report->id]),
            'category' => 'payment',
            'icon' => 'o-receipt-percent',
        ];
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }
}
