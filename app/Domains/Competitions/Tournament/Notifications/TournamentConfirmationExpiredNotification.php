<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\Competitions\Tournament\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentConfirmationExpiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Confirmation expire : :name', ['name' => $this->tournament->name]),
            'body' => __('See the tournament details'),
            'url' => route('admin.tournaments.index'),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your reserved spot has expired') . ' — ' . $this->tournament->name)
            ->greeting(__('Hi') . ' ' . $notifiable->first_name . ' !')
            ->line(__('A spot had opened up for you in **:tournament**, but the confirmation window has now passed.', [
                'tournament' => $this->tournament->name,
            ]))
            ->line(__('Your spot has been offered to the next person on the waiting list.'))
            ->line(__('If you are still interested in participating, please contact us so we can check if a spot becomes available again.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
