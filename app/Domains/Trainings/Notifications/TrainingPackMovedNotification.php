<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le club a déplacé le membre d'un pack vers un autre.
 *
 * Une seule notification, et non le couple « retiré de A » puis « ajouté à B » :
 * pour le membre, changer de groupe est un événement, pas deux. Recomposer le
 * message à partir de {@see TrainingPackCancelledNotification} et de
 * {@see TrainingPackAddedByClubNotification} lui annoncerait un départ qu'il
 * n'a pas subi, suivi d'une inscription qu'il n'a pas demandée.
 *
 * Aucun montant ici : `amount_due` est le prix de la saison entière, et le
 * citer réclamait à une famille à jour ce qu'elle avait déjà payé. Un
 * complément part dans sa propre invitation au paiement, qui lit le solde de
 * sa ligne.
 */
class TrainingPackMovedNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public readonly TrainingPack $from,
        public readonly TrainingPack $to,
        public readonly Subscription $subscription,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Moved to another training pack'),
            'body' => __('The club moved you from :from to :to', [
                'from' => $this->from->name,
                'to' => $this->to->name,
            ]),
            'url' => $this->memberTrainingsUrl($notifiable),
            'category' => 'training',
            'icon' => 'o-arrows-right-left',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Training — you now train with :pack', ['pack' => $this->to->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('The club moved you from pack **:from** to pack **:to** for season **:season**. Your spot in the new pack is confirmed — there is nothing for you to do.', [
                'from' => $this->from->name,
                'to' => $this->to->name,
                'season' => $this->subscription->season->name,
            ]))
            ->line(__('If this move is a mistake, contact the club secretariat and we will undo it.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
