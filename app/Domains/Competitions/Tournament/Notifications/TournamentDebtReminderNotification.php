<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubEvents\Tournament\Tournament;
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
            ->line(__('IBAN: BE23 7323 3320 8791 — BIC: CREGBEBB — Beneficiary: CTT Ottignies-Blocry ASBL'))
            ->line('---')
            ->line(__('Please settle your payment as soon as possible. Contact us if you have any questions.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
