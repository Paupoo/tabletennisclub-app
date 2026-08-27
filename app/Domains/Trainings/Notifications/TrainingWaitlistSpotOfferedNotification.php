<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Shared\Traits\LinksToMemberSpace;
use App\Domains\Trainings\Models\TrainingPack;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingWaitlistSpotOfferedNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public TrainingPack $pack,
        public Carbon $deadline,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('A training spot is available'),
            'body' => __('See the training details'),
            'url' => $this->memberTrainingsUrl($notifiable),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('A spot is available — :pack', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('A spot has opened up for **:pack**!', ['pack' => $this->pack->name]))
            ->line(__('You have until **:deadline** to confirm your spot. After that, it will be offered to the next person on the waiting list.', [
                'deadline' => $this->deadline->format('d/m/Y H:i'),
            ]))
            ->action(__('Confirm my spot'), $this->memberTrainingsUrl($notifiable))
            ->salutation(__('The club team'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
