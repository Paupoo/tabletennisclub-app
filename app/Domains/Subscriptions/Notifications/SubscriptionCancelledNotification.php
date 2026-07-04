<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly float $refundAmount = 0.0,
        public readonly string $message = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Affiliation annulée'),
            'body' => __('Votre cotisation a été annulée'),
            'url' => '#',
            'category' => 'subscription',
            'icon' => 'o-identification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;

        $mail = (new MailMessage)
            ->subject(__('Affiliation :season — annulation confirmée', ['season' => $season->name]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line(__('Votre cotisation pour la saison **:season** a été annulée.', ['season' => $season->name]));

        if ($this->refundAmount > 0) {
            $mail->line(__('Un remboursement de **:amount €** va vous être effectué.', [
                'amount' => number_format($this->refundAmount, 2),
            ]));

            if ($notifiable->iban) {
                $mail->line(__('Le remboursement sera versé sur le compte :iban.', ['iban' => $notifiable->iban]));
            } else {
                $mail->line(__('Nous n\'avons pas de compte bancaire enregistré à votre nom : merci de communiquer votre IBAN au secrétariat pour recevoir le remboursement.'));
            }
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
        return ['mail', 'database'];
    }
}
