<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The export could not be built. It used to turn `failed` in silence, and the
 * requester kept waiting for a bell that would never ring.
 */
class ExpenseReportExportFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public ExpenseReportExport $export) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your expense reports export failed'),
            'body' => __('Run it again from the expense reports.'),
            'url' => route('admin.treasury.expense-reports'),
            'category' => 'payment',
            'icon' => 'o-exclamation-triangle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your expense reports export failed'))
            ->line(__('The export you asked for could not be built.'))
            ->action(__('Run it again'), route('admin.treasury.expense-reports'))
            ->line(__('If it fails again, tell the site administrator.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
