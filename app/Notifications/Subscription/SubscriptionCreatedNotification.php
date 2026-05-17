<?php

declare(strict_types=1);

namespace App\Notifications\Subscription;

use App\Models\ClubAdmin\Subscription\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;
        $formula = $this->subscription->is_competitive
            ? __('Competitive')
            : __('Recreational');

        $trainingPacks = $this->subscription->trainingPacks;

        $message = (new MailMessage)
            ->subject(__('Affiliation :season — demande enregistrée', ['season' => $season->name]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line(__('Votre demande d\'affiliation pour la saison **:season** a bien été enregistrée.', ['season' => $season->name]))
            ->line(__('Le secrétaire du club va la traiter prochainement. Vous recevrez un email dès que votre dossier sera confirmé avec les informations de paiement.'))
            ->line('---')
            ->line(__('**Récapitulatif de votre demande :**'))
            ->line(__('Formule : :formula', ['formula' => $formula]));

        if ($trainingPacks->isNotEmpty()) {
            $packNames = $trainingPacks->pluck('name')->join(', ');
            $message->line(__('Entraînements : :packs', ['packs' => $packNames]));
        }

        return $message
            ->line(__('Montant estimé : :amount €', ['amount' => number_format($this->subscription->amount_due, 2, ',', ' ')]))
            ->line(__('En cas de question, n\'hésitez pas à contacter le secrétariat du club.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
