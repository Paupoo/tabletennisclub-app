<?php

declare(strict_types=1);

namespace App\Notifications\Training;

use App\Models\ClubEvents\Training\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingWaitlistJoinedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TrainingPack $pack,
        public int $position,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'training_pack_id' => $this->pack->id,
            'waitlist_position' => $this->position,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Training waitlist — :pack', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('The training **:pack** is currently full. You have been added to the waiting list at position **#:position**.', [
                'pack' => $this->pack->name,
                'position' => $this->position,
            ]))
            ->line(__('We will notify you as soon as a spot becomes available.'))
            ->salutation(__('The club team'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
