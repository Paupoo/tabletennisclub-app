<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class ExpenseReportRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ExpenseReport $report) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Expense report rejected'),
            'body' => $this->report->decision_reason,
            'url' => route('admin.user.expense-reports', ['user' => $this->report->user_id, 'report' => $this->report->id]),
            'category' => 'payment',
            'icon' => 'o-x-circle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Expense report rejected'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Your expense report ":description" (:amount €) has been rejected.', [
                'description' => $this->report->description,
                'amount' => number_format($this->report->amount, 2, ',', ' '),
            ]))
            ->line(__('Reason: :reason', ['reason' => $this->report->decision_reason]))
            ->line(__('If you can fix it, resume the report: the form opens already filled in, you only add the right proof.'))
            ->action(__('Resume this report'), route('admin.user.expense-reports', ['user' => $this->report->user_id, 'resume' => $this->report->id]));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
