<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The export asked for is ready — in the bell and by mail, with its link, for
 * a week.
 *
 * The bell alone was not enough: it does not refresh on its own, so whoever
 * waited on the page never saw it ring, and nothing else pointed at the file.
 * The link still requires signing in as the requester.
 */
class ExpenseReportExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public ExpenseReportExport $export) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => __('Available until :date.', ['date' => $this->export->expires_at?->format('d/m/Y')]),
            'url' => route('admin.expense-reports.export', $this->export),
            'category' => 'payment',
            'icon' => 'o-arrow-down-tray',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->export->isZip()
                ? __('The expense reports archive you asked for is ready.')
                : __('The expense reports PDF you asked for is ready.'))
            ->action(__('Download'), route('admin.expense-reports.export', $this->export))
            ->line(__('Available until :date.', ['date' => $this->export->expires_at?->format('d/m/Y')]))
            ->line(__('The link only works for you, once signed in.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function title(): string
    {
        return $this->export->isZip() ? __('Your expense reports archive is ready') : __('Your expense reports PDF is ready');
    }
}
