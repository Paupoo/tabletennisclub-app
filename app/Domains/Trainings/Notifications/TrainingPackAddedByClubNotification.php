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
 * Le club a inscrit le membre lui-même, sans demande de sa part.
 *
 * Distincte de {@see TrainingPackRequestedNotification}, qui annonce « votre
 * demande a bien été reçue » : ici le membre n'a rien demandé, et le message
 * doit lui dire ce qu'on a fait à sa place — et ce que ça lui coûte.
 */
class TrainingPackAddedByClubNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public readonly TrainingPack $pack,
        public readonly Subscription $subscription,
        public readonly ?string $paymentReference = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Enrolled in a training pack'),
            'body' => __('The club enrolled you in :pack', ['pack' => $this->pack->name]),
            'url' => $this->memberTrainingsUrl($notifiable),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Training :pack — the club enrolled you', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('The club has enrolled you in pack **:pack** for season **:season**. Your spot is confirmed — there is nothing for you to do.', [
                'pack' => $this->pack->name,
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
            ->line(__('If this enrolment is a mistake, contact the club secretariat and we will undo it.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
