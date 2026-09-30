<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Notifications;

use App\Domains\ClubAdmin\Finance\Models\FinancialExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The export could not be built. It used to turn `failed` in silence, and the
 * requester kept waiting for a bell that would never ring. The link goes back
 * to the year's « Pièces & exports » tab, where « Relancer » is.
 */
class FinancialExportFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public FinancialExport $export) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your financial report export failed'),
            'body' => __('Run it again from the financial report.'),
            'url' => $this->url(),
            'category' => 'payment',
            'icon' => 'o-exclamation-triangle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your financial report export failed'))
            ->line(__('The export you asked for could not be built.'))
            ->action(__('Run it again'), $this->url())
            ->line(__('If it fails again, tell the site administrator.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function url(): string
    {
        return route('admin.treasury.report', array_filter(['year' => $this->export->fiscal_year, 'tab' => 'pieces']));
    }
}
