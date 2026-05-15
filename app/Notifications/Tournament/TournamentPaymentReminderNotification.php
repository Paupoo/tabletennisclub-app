<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubEvents\Tournament\Tournament;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentPaymentReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public Payment $payment,
        public Carbon $deadline,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hoursLeft = (int) now()->diffInHours($this->deadline, false);

        return (new MailMessage)
            ->subject(__('Payment reminder') . ' — ' . $this->tournament->name)
            ->greeting(__('Reminder') . ', ' . $notifiable->first_name . ' !')
            ->line(__('Your payment of **:amount €** for :name is still pending.', [
                'amount' => number_format($this->payment->amount_due, 2, ',', ' '),
                'name' => $this->tournament->name,
            ]))
            ->line(__('You have :hours hours left to pay (deadline: :deadline).', [
                'hours' => $hoursLeft,
                'deadline' => $this->deadline->format('d/m/Y à H:i'),
            ]))
            ->line('---')
            ->line(__('Structured reference: :ref', ['ref' => $this->payment->reference]))
            ->line(__('IBAN: BE23 7323 3320 8791 — BIC: CREGBEBB'))
            ->line('---')
            ->line(__('After the deadline, your registration will be cancelled automatically.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
