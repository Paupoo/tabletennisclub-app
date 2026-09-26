<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class ExpenseReportPaidNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ExpenseReport $report) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Expense report paid'),
            'body' => $this->report->description,
            'url' => route('admin.user.expense-reports', $this->report->user_id),
            'category' => 'payment',
            'icon' => 'o-banknotes',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Expense report paid'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('The club has refunded :amount € for your expense report ":description".', [
                'amount' => number_format((float) $this->report->accepted_amount, 2, ',', ' '),
                'description' => $this->report->description,
            ]))
            ->line(__('The transfer went to the account ending in :suffix.', ['suffix' => substr((string) $this->report->refund_iban, -4)]))
            ->action(__('See my expense reports'), route('admin.user.expense-reports', $this->report->user_id));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
