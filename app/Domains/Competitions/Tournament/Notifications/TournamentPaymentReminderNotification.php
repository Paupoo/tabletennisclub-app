<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentPaymentReminderNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public Tournament $tournament,
        public Payment $payment,
        public Carbon $deadline,
        public bool $isOverdue = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Payment reminder: :name', ['name' => $this->tournament->name]),
            'body' => __('See the tournament details'),
            'url' => $this->memberEventsUrl($notifiable),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $club = Club::ourClub()->first();
        $amount = number_format($this->payment->amount_due, 2, ',', ' ');

        $message = (new MailMessage)
            ->subject(__('Payment reminder') . ' — ' . $this->tournament->name)
            ->greeting(__('Reminder') . ', ' . $notifiable->first_name . ' !')
            ->line(__('Your payment of **:amount €** for :name is still pending.', [
                'amount' => $amount,
                'name' => $this->tournament->name,
            ]));

        $message = $this->isOverdue
            ? $message->line(__('The payment deadline (:deadline) has passed. Your spot is kept: you can still pay by bank transfer, or at the desk on the tournament day.', [
                'deadline' => $this->deadline->format('d/m/Y'),
            ]))
            : $message->line(__('Please pay by :deadline.', [
                'deadline' => $this->deadline->format('d/m/Y'),
            ]));

        return $message
            ->line('---')
            ->line(__('Structured reference: :ref', ['ref' => $this->payment->reference]))
            ->line(__('payment.iban_bic_line', ['iban' => $club->bank_account_formatted, 'bic' => $club->bic]))
            ->line('---')
            ->line(__('If you have already paid by the time you receive this message, please ignore this reminder.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
