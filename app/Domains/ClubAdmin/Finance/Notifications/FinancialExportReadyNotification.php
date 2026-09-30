<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Notifications;

use App\Domains\ClubAdmin\Finance\Models\FinancialExport;
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
class FinancialExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public FinancialExport $export) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => __('Available until :date.', ['date' => $this->export->expires_at?->format('d/m/Y')]),
            'url' => route('admin.treasury.exports.download', $this->export),
            'category' => 'payment',
            'icon' => 'o-arrow-down-tray',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line(__('What it holds: :summary.', ['summary' => $this->export->summary()]))
            ->action(__('Download'), route('admin.treasury.exports.download', $this->export))
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
        $year = (string) $this->export->year()?->label();

        return $this->export->isZip()
            ? __('Your :year financial archive is ready', ['year' => $year])
            : __('Your :year financial report PDF is ready', ['year' => $year]);
    }
}
