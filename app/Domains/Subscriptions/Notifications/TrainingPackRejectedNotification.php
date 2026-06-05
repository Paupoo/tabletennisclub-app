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
            'title' => __('Pack d\'entraînement rejeté'),
            'body' => __('Votre demande d\'inscription a été refusée'),
            'url' => '#',
            'category' => 'subscription',
            'icon' => 'o-identification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;

        $mail = (new MailMessage)
            ->subject(__('Entraînement :season — demande refusée', ['season' => $season->name]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line(__('Votre demande d\'inscription au pack **:pack** pour la saison **:season** a malheureusement été refusée.', [
                'pack' => $this->pack->name,
                'season' => $season->name,
            ]));

        if ($this->template === 'level') {
            $mail->line(__('**Raison :** le niveau de ce groupe ne correspond pas à votre profil (trop fort ou trop faible).'));
        } elseif ($this->template === 'full_teams') {
            $mail->line(__('**Raison :** les équipes de compétition sont complètes pour cette saison.'));
        }

        if (! empty($this->message)) {
            $mail->line('---')
                ->line(__('**Message du secrétariat :**'))
                ->line($this->message);
        }

        return $mail
            ->line('---')
            ->line(__('N\'hésitez pas à contacter le secrétariat pour toute question ou pour demander une place dans un autre groupe.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
