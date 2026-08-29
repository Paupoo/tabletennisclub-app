<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentWaitlistRemovedNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public Tournament $tournament,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Removed from the waiting list: :name', ['name' => $this->tournament->name]),
            'body' => __('See the tournament details'),
            'url' => $this->memberEventsUrl($notifiable),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Waiting list update') . ' — ' . $this->tournament->name)
            ->greeting(__('Hi') . ' ' . $notifiable->first_name . ' !')
            ->line(__('Your waiting list entry for :name has been removed by an administrator.', [
                'name' => '**' . $this->tournament->name . '**',
            ]))
            ->line(__('If you believe this is an error, please contact us.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
