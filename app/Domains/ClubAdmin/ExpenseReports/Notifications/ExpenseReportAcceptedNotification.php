<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class ExpenseReportAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ExpenseReport $report) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Expense report accepted'),
            'body' => $this->report->description,
            'url' => route('admin.user.expense-reports', $this->report->user_id),
            'category' => 'payment',
            'icon' => 'o-check-circle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Expense report accepted'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Your expense report ":description" has been accepted.', ['description' => $this->report->description]))
            ->line(__('Amount to be refunded: :amount €', ['amount' => number_format((float) $this->report->accepted_amount, 2, ',', ' ')]));

        if ((float) $this->report->accepted_amount < $this->report->amount) {
            $mail->line(__('You declared :declared €. Reason for the difference: :reason', [
                'declared' => number_format($this->report->amount, 2, ',', ' '),
                'reason' => $this->report->decision_reason,
            ]));
        }

        return $mail
            ->line(__('The treasurer will wire the amount to your account.'))
            ->action(__('See my expense reports'), route('admin.user.expense-reports', $this->report->user_id));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
