<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class NewTournamentPublishedNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

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
            'body' => __('A new tournament has been published'),
            'url' => $this->memberEventsUrl($notifiable),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        /*
         * Placeholders, not concatenation. `__('Join us at ' . $name)` builds a
         * different translation key for every tournament, so none of them ever
         * matched an entry and the mail went out in English. It had never been
         * noticed because the announcement itself never fired (issue #81).
         */
        $invitation = __('Join us at :name on :date', [
            'name' => $this->tournament->name,
            'date' => $this->tournament->start_date->format('d/m/Y'),
        ]);

        return (new MailMessage)
            ->subject($invitation)
            ->greeting(__('Hi :name!', ['name' => $this->user->first_name]))
            ->line($invitation)
            ->line(__('Click on the button below to join us and play your best table tennis!'))
            ->action(__('I want to play'), URL::signedRoute(
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
        // Optional notification: honour the member's opt-out preference.
        if ($notifiable instanceof User && ! $notifiable->wantsNotification('new_tournaments')) {
            return [];
        }

        return ['mail', 'database'];
    }
}
