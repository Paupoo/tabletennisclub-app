<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\Competitions\Tournament\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentWaitlistAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public int $position,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Added to the waiting list: :name', ['name' => $this->tournament->name]),
            'body' => __('See the tournament details'),
            'url' => route('admin.tournaments.index'),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Waiting list') . ' — ' . $this->tournament->name)
            ->greeting(__('Hi') . ' ' . $notifiable->first_name . ' !')
            ->line(__('The tournament :name is currently full.', ['name' => '**' . $this->tournament->name . '**']))
            ->line(__('You have been placed on the waiting list at position :position.', ['position' => $this->position]))
            ->line(__('You will be notified by email as soon as a spot becomes available.'))
            ->line(__('Thank you for your patience!'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
