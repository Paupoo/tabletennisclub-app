<?php

namespace App\Notifications;

use App\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTournamentPublishedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Tournament $tournament)
    {
        //
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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Register to ' . $this->tournament->name . ' on the ' . $this->tournament->start_date->format('d/m/Y')))
            ->greeting(__('Register to ' . $this->tournament->name . ' on the ' . $this->tournament->start_date->format('d/m/Y')))
            ->line(__('Please click on the button below to join us and play some table tennis!'))
            ->action('Notification Action', 'http://localhost:8000/admin/tournament/' . $this->tournament->id)
            ->line(__('We are looking forward to see you there!'));
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
}
