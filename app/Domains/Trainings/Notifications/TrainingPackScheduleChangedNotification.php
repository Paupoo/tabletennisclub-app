<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Shared\Traits\LinksToMemberSpace;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a pack keeps running but moves: another day, hour or room.
 *
 * Only a real slot change triggers this. Renaming a pack or correcting its
 * price changes nothing for the member, and mailing them about it is how a
 * club teaches its members to ignore its mail.
 */
class TrainingPackScheduleChangedNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public TrainingPack $pack,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Training schedule changed'),
            'body' => __(':pack has a new schedule', ['pack' => $this->pack->name]),
            'url' => $this->memberTrainingsUrl($notifiable),
            'category' => 'training',
            'icon' => 'o-calendar-days',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('New schedule — :pack', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('The schedule of **:pack** has changed. Your spot is unchanged — only the times are.', ['pack' => $this->pack->name]));

        if ($this->pack->start_time) {
            $mail->line(__('From now on: **:day at :time**, in :room.', [
                'day' => $this->dayName(),
                'time' => substr($this->pack->start_time, 0, 5),
                'room' => $this->pack->room?->name ?? __('the usual room'),
            ]));
        }

        return $mail
            ->line(__('Please check the calendar for the exact dates of the sessions to come.'))
            ->action(__('See my trainings'), $this->memberTrainingsUrl($notifiable))
            ->salutation(__('The club team'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function dayName(): string
    {
        $days = [
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
        ];

        return $days[$this->pack->day_of_week] ?? __('the usual day');
    }
}
