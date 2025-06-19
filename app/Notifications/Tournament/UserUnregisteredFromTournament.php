<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserUnregisteredFromTournament extends Notification
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
        return (new MailMessage)->subject('Confirmation : you are unregistered from ' . $this->tournament->name)
            ->markdown('mail.user-unregistered-from-tournament', [
                'tournament' => $this->tournament,
                'user' => $this->user,
            ]);
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
