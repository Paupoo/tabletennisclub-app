<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingPackCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TrainingPack $pack,
        public readonly Subscription $subscription,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Pack d\'entraînement annulé'),
            'body' => __('Votre demande d\'inscription a bien été annulée'),
            'url' => route('admin.trainings.index'),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Entraînement :pack — demande annulée', ['pack' => $this->pack->name]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line(__('Votre demande d\'inscription au pack **:pack** pour la saison **:season** a bien été annulée.', [
                'pack' => $this->pack->name,
                'season' => $this->subscription->season->name,
            ]))
            ->line(__('Vous pouvez soumettre une nouvelle demande à tout moment depuis votre espace membre.'))
            ->line(__('En cas de question, n\'hésitez pas à contacter le secrétariat du club.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
