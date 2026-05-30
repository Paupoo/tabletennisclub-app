<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRegisteredToTournament extends Notification implements ShouldQueue
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
            'title' => __('Inscription au tournoi : :name', ['name' => $this->tournament->name]),
            'body' => __('Vous êtes inscrit au tournoi'),
            'url' => route('admin.tournaments.index'),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Confirmation : you are registered to ' . $this->tournament->name)
            ->markdown('mail.user-registered-to-tournament', [
                'user' => $this->user,
                'tournament' => $this->tournament,
            ]);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
