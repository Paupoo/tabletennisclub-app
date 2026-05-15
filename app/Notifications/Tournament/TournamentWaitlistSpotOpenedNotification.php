<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Models\ClubEvents\Tournament\Tournament;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TournamentWaitlistSpotOpenedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public int $userId,
        public Carbon $deadline,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $confirmUrl = URL::signedRoute(
            'tournament.register.email',
            ['tournament' => $this->tournament->id, 'user' => $this->userId],
            $this->deadline,
        );

        $leaveUrl = URL::signedRoute(
            'tournament.leave-waitlist.email',
            ['tournament' => $this->tournament->id, 'user' => $this->userId],
            $this->deadline,
        );

        return (new MailMessage)
            ->subject(__('A spot is available!') . ' — ' . $this->tournament->name)
            ->greeting(__('Great news') . ', ' . $notifiable->first_name . ' !')
            ->line(__('A spot has opened up in the tournament :name.', ['name' => '**' . $this->tournament->name . '**']))
            ->line(__('You have until :deadline to confirm your participation.', [
                'deadline' => $this->deadline->format('d/m/Y') . ' ' . __('at') . ' ' . $this->deadline->format('H:i'),
            ]))
            ->action(__('Yes, I confirm my participation'), $confirmUrl)
            ->line(__('If you can no longer participate, you can withdraw using the link below — your spot will immediately be offered to the next person.'))
            ->line('[' . __('No, I withdraw from the waitlist') . '](' . $leaveUrl . ')')
            ->line(__('If you do not respond within 48 hours, your spot will automatically be offered to the next person.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
