<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingPackCancelledNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public readonly TrainingPack $pack,
        public readonly Subscription $subscription,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Training pack cancelled'),
            'body' => __('Your enrolment request has been cancelled'),
            'url' => $this->memberTrainingsUrl($notifiable),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Training :pack — request cancelled', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Your enrolment request for pack **:pack** for season **:season** has been cancelled.', [
                'pack' => $this->pack->name,
                'season' => $this->subscription->season->name,
            ]))
            ->line(__('You can submit a new request at any time from your member area.'))
            ->line(__('If you have any question, feel free to contact the club secretariat.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
