<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentDebtReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public Payment $payment,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Rappel de dette : :name', ['name' => $this->tournament->name]),
            'body' => __('Consultez les détails du tournoi'),
            'url' => route('admin.tournaments.index'),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $club = Club::ourClub()->first();

        return (new MailMessage)
            ->subject(__('Payment reminder') . ' — ' . $this->tournament->name)
            ->greeting(__('Hello') . ', ' . $notifiable->first_name . ' !')
            ->line(__('We noticed your registration fee for :name has not been paid yet.', [
                'name' => $this->tournament->name,
            ]))
            ->line(__('Amount due: **:amount €**', [
                'amount' => number_format($this->payment->amount_due, 2, ',', ' '),
            ]))
            ->line('---')
            ->line(__('Structured reference: :ref', ['ref' => $this->payment->reference]))
            ->line(__('payment.iban_bic_beneficiary_line', ['iban' => $club->bank_account_formatted, 'bic' => $club->bic, 'name' => $club->name]))
            ->line('---')
            ->line(__('Please settle your payment as soon as possible. Contact us if you have any questions.'))
            ->line(__('If you have already paid by the time you receive this message, please ignore this reminder.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
