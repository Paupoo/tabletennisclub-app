<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingPackRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly TrainingPack $pack,
        public readonly string $message = '',
        public readonly string $template = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Training pack rejected'),
            'body' => __('Your training enrolment request has been rejected'),
            'url' => '#',
            'category' => 'subscription',
            'icon' => 'o-identification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;

        $mail = (new MailMessage)
            ->subject(__('Training :season — request rejected', ['season' => $season->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Your enrolment request for pack **:pack** for season **:season** has unfortunately been rejected.', [
                'pack' => $this->pack->name,
                'season' => $season->name,
            ]));

        if ($this->template === 'level') {
            $mail->line(__('**Reason:** this group\'s level does not match your profile (too strong or too weak).'));
        } elseif ($this->template === 'full_teams') {
            $mail->line(__('**Reason:** the competition teams are full for this season.'));
        }

        if (! empty($this->message)) {
            $mail->line('---')
                ->line(__('**Message from the secretariat:**'))
                ->line($this->message);
        }

        return $mail
            ->line('---')
            ->line(__('Feel free to contact the secretariat with any question, or to ask for a spot in another group.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
