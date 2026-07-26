<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
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
        return [
            'title' => __('Payment reminder: :name', ['name' => $this->tournament->name]),
            'body' => __('See the tournament details'),
            'url' => route('admin.tournaments.index'),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hoursLeft = (int) now()->diffInHours($this->deadline, false);
        $club = Club::ourClub()->first();

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
            ->line(__('payment.iban_bic_line', ['iban' => $club->bank_account_formatted, 'bic' => $club->bic]))
            ->line('---')
            ->line(__('After the deadline, your registration will be cancelled automatically.'))
            ->line(__('If you have already paid by the time you receive this message, please ignore this reminder.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
