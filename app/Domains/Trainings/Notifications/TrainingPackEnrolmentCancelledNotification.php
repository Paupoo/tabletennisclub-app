<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Actions\ClubAdmin\Subscriptions\CancelTrainingPackEnrolmentAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Une inscription posée par erreur a été annulée.
 *
 * Le membre a été prévenu de l'inscription, et souvent invité à payer : sans ce
 * message, il garde une communication structurée valide en main et risque de
 * la payer. Le message dit donc ce qui change pour son argent — demande
 * réduite, remboursement à venir — et rien d'autre.
 *
 * @see CancelTrainingPackEnrolmentAction
 */
class TrainingPackEnrolmentCancelledNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public readonly TrainingPack $pack,
        public readonly Subscription $subscription,
        public readonly float $reducedAmount,
        public readonly float $refundAmount,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Training enrolment cancelled'),
            'body' => __('Your enrolment in :pack was recorded by mistake and has been cancelled.', ['pack' => $this->pack->name]),
            'url' => $this->memberPackUrl($notifiable, $this->pack),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Training :pack — enrolment cancelled', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Your enrolment in pack **:pack** for season **:season** was recorded by mistake. We have cancelled it.', [
                'pack' => $this->pack->name,
                'season' => $this->subscription->season->name,
            ]));

        if ($this->reducedAmount > 0) {
            $mail->line(__('The amount we were asking you to pay has been lowered by :amount €. Please do not pay the previous request.', [
                'amount' => number_format($this->reducedAmount, 2, ',', ' '),
            ]));
        }

        if ($this->refundAmount > 0) {
            $mail->line(__('You will be refunded :amount €.', [
                'amount' => number_format($this->refundAmount, 2, ',', ' '),
            ]));
        }

        return $mail->line(__('We apologise for the inconvenience.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
