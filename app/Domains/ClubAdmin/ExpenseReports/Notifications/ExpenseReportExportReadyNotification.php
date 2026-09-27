<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** The export asked for is ready — in the bell, with its link, for a week. */
class ExpenseReportExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public ExpenseReportExport $export) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->export->isZip() ? __('Your expense reports archive is ready') : __('Your expense reports PDF is ready'),
            'body' => __('Available until :date.', ['date' => $this->export->expires_at?->format('d/m/Y')]),
            'url' => route('admin.expense-reports.export', $this->export),
            'category' => 'payment',
            'icon' => 'o-arrow-down-tray',
        ];
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }
}
