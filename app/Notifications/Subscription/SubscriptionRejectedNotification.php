<?php

declare(strict_types=1);

namespace App\Notifications\Subscription;

use App\Models\ClubAdmin\Subscription\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $message = '',
        public readonly string $template = '',
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;

        $mail = (new MailMessage)
            ->subject(__('Affiliation :season — demande refusée', ['season' => $season->name]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line(__('Votre demande d\'affiliation pour la saison **:season** a malheureusement été refusée.', ['season' => $season->name]));

        if ($this->template === 'level') {
            $mail->line(__('**Raison :** le niveau demandé ne correspond pas aux prérequis du groupe (trop fort ou trop faible).'));
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
            ->line(__('N\'hésitez pas à contacter le secrétariat pour toute question ou pour soumettre une nouvelle demande.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
