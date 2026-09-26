<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Paid reports whose proofs still live on the server only.
 *
 * Quarterly, and once more in January when the closed year goes to the
 * auditors: a server that dies takes the receipts of the accounts with it.
 */
class ExpenseReportsToArchiveNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $count,
        public float $total,
        public bool $yearEnd = false,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->yearEnd
            ? __('Financial year :year closed: expense reports to archive', ['year' => now()->year - 1])
            : __('Expense reports to archive');

        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(trans_choice(
                ':count paid expense report (:amount €) has not been archived yet.|:count paid expense reports (:amount €) have not been archived yet.',
                $this->count,
                ['amount' => number_format($this->total, 2, ',', ' ')],
            ))
            ->line(__('Their proofs only exist on the application\'s server. Download the ZIP archive and keep it with the club\'s accounts.'))
            ->action(__('Archive the paid expense reports'), route('admin.treasury.expense-reports', ['tab' => 'paid', 'unarchived' => 1]));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
