<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class TeamCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Team $team) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('New team created'),
            'body' => __('Team ":name" has been created', ['name' => $this->team->name]),
            'url' => route('admin.interclubs.teams.show', $this->team),
            'category' => 'interclub',
            'icon' => 'o-users',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New team: :name', ['name' => $this->team->name]))
            ->greeting(__('Hi :name!', ['name' => $notifiable->first_name]))
            ->line(__('A new interclub team has been created.'))
            ->line('---')
            ->line(__('**Team Details:**'))
            ->line(__('Name: :name', ['name' => $this->team->name]))
            ->line(__('Captain: :captain', ['captain' => $this->team->captain->full_name ?? 'TBD']))
            ->line(__('League: :league', ['league' => $this->team->league?->name ?? 'TBD']))
            ->action(__('View team'), route('admin.interclubs.teams.show', $this->team))
            ->line(__('Thank you!'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
