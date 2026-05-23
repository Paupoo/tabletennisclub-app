<?php

declare(strict_types=1);

namespace App\Notifications\Training;

use App\Models\ClubEvents\Training\TrainingPack;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingWaitlistSpotOfferedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TrainingPack $pack,
        public Carbon $deadline,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'training_pack_id' => $this->pack->id,
            'confirmation_deadline' => $this->deadline->toISOString(),
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
            ->action(__('Confirm my spot'), url('/'))
            ->salutation(__('The club team'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
