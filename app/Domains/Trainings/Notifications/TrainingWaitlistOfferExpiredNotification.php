<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a waitlist offer lapses and the member loses the spot.
 *
 * Losing the spot is the intended policy; losing it without being told is not.
 * The member is removed from the pack entirely, so nothing on their own pages
 * would otherwise show that the offer ever existed.
 */
class TrainingWaitlistOfferExpiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TrainingPack $pack,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Training spot expired'),
            'body' => __('The spot offered to you for :pack was not confirmed in time', ['pack' => $this->pack->name]),
            'url' => route('admin.trainings.index'),
            'category' => 'training',
            'icon' => 'o-clock',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your spot for :pack has expired', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('The spot we offered you for **:pack** was not confirmed in time, so it has been passed on to the next person on the waiting list.', ['pack' => $this->pack->name]))
            ->line(__('You are no longer on the waiting list for this training. If you are still interested, you can ask to join it again.'))
            ->action(__('See the trainings'), url('/'))
            ->salutation(__('The club team'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
