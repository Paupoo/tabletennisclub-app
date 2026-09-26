<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Notifications;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sunday evening: every report still waiting for this decider, oldest first,
 * so that nothing sleeps in the list. Mail only — the bell already rang for
 * each one as it came in.
 */
class ExpenseReportsDigestNotification extends Notification
{
    use Queueable;

    /** @param Collection<int, ExpenseReport> $reports */
    public function __construct(public Collection $reports) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(trans_choice(':count expense report to decide|:count expense reports to decide', $this->reports->count()))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('These expense reports are waiting for a decision:'));

        foreach ($this->reports as $report) {
            $days = (int) $report->created_at?->startOfDay()->diffInDays(now()->startOfDay());
            $age = $days < 7 ? __('new this week') : __('waiting for :days days', ['days' => $days]);

            $mail->line(sprintf(
                '• **%s** — %s, %s € (%s) — %s',
                $report->user->full_name,
                $report->description,
                number_format($report->amount, 2, ',', ' '),
                $report->category->label(),
                $age,
            ));
        }

        return $mail
            ->line(__('Total to decide: :amount €', ['amount' => number_format($this->reports->sum('amount'), 2, ',', ' ')]))
            ->action(__('Decide on the expense reports'), route('admin.treasury.expense-reports'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
