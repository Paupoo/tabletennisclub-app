<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Tournoi annulé : :name', ['name' => $this->tournament->name]),
            'body' => __('Le tournoi a été annulé'),
            'url' => route('admin.tournaments.index'),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':tournament — Cancellation notice', ['tournament' => $this->tournament->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('We regret to inform you that **:tournament** scheduled on **:date** has been cancelled.', [
                'tournament' => $this->tournament->name,
                'date' => $this->tournament->start_date?->format('d/m/Y') ?? '—',
            ]))
            ->line(__('We apologise for any inconvenience. Please contact the club if you have any questions.'))
            ->salutation(__('The club team'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
