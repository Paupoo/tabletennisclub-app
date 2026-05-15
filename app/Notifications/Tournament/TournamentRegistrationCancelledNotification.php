<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentRegistrationCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Registration cancelled') . ' — ' . $this->tournament->name)
            ->greeting(__('Hi') . ' ' . $notifiable->first_name . ' !')
            ->line(__('Your registration for :name has been cancelled.', [
                'name' => '**' . $this->tournament->name . '**',
            ]));

        if ($this->tournament->start_date) {
            $message->line(__('The tournament is scheduled for :date.', [
                'date' => $this->tournament->start_date->format('d/m/Y'),
            ]));
        }

        return $message->line(__('If you have any questions, feel free to contact us.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
