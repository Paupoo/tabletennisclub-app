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
 * Le solde annoncé est celui d'après recalcul : un déplacement peut ne rien
 * coûter, réclamer un complément ou ouvrir un remboursement, et c'est la seule
 * chose que le membre a besoin de lire.
 */
class TrainingPackMovedNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public readonly TrainingPack $from,
        public readonly TrainingPack $to,
        public readonly Subscription $subscription,
        public readonly ?string $paymentReference = null,
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
            ->line(__('The amount now due for your membership is **:amount €**.', [
                'amount' => number_format((float) $this->subscription->amount_due, 2),
            ]))
            ->when(
                $this->paymentReference !== null,
                fn (MailMessage $mail): MailMessage => $mail->line(__('Please quote the structured reference :reference with your transfer.', [
                    'reference' => $this->paymentReference,
                ])),
            )
            ->line(__('If this move is a mistake, contact the club secretariat and we will undo it.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
