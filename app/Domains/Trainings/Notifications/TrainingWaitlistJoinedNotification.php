<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Trainings\Models\TrainingPack;
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
            'title' => __('Joined the waiting list'),
            'body' => __('See the training details'),
            'url' => route('admin.trainings.index'),
            'category' => 'training',
            'icon' => 'o-academic-cap',
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
        return ['mail', 'database'];
    }
}
