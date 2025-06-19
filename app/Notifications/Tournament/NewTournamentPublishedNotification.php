<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTournamentPublishedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Tournament $tournament, public User $user)
    {
        //
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Join us at ' . $this->tournament->name . ' on the ' . $this->tournament->start_date->format('d/m/Y')))
            ->greeting(__('Hi ' . $this->user->first_name . ' !'))
            ->line(__('Join us at ' . $this->tournament->name . ' on the ' . $this->tournament->start_date->format('d/m/Y')))
            ->line(__('Click on the button below to join us and play your best table tennis!'))
            ->action('I want to play', 'http://localhost:8000/admin/tournament/' . $this->tournament->id . '/register/' . $this->user->id)
            ->line(__('We are looking forward to see you there!'));
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
