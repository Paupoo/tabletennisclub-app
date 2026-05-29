<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

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
            'title' => __('Nouveau tournoi : :name', ['name' => $this->tournament->name]),
            'body' => __('Un nouveau tournoi a été publié'),
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
        return (new MailMessage)
            ->subject(__('Join us at ' . $this->tournament->name . ' on the ' . $this->tournament->start_date->format('d/m/Y')))
            ->greeting(__('Hi ' . $this->user->first_name . ' !'))
            ->line(__('Join us at ' . $this->tournament->name . ' on the ' . $this->tournament->start_date->format('d/m/Y')))
            ->line(__('Click on the button below to join us and play your best table tennis!'))
            ->action('I want to play', URL::signedRoute(
                'tournament.register.email',
                ['tournament' => $this->tournament->id, 'user' => $this->user->id],
                now()->addDays(7),
            ))
            ->line(__('We are looking forward to see you there!'));
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
