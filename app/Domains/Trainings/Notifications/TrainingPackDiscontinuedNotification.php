<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the club stops running a pack outright, mid-season.
 *
 * Distinct from {@see TrainingPackCancelledNotification}, which concerns one
 * member leaving a pack that carries on without them.
 */
class TrainingPackDiscontinuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TrainingPack $pack,
        public ?string $reason = null,
        public float $refundAmount = 0.0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Entraînement arrêté'),
            'body' => __(':pack ne se tiendra plus', ['pack' => $this->pack->name]),
            'url' => route('admin.trainings.index'),
            'category' => 'training',
            'icon' => 'o-x-circle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Training stopped — :pack', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('The club has had to stop **:pack**. The remaining sessions will not take place.', ['pack' => $this->pack->name]));

        if ($this->reason) {
            $mail->line(__('Reason given: :reason', ['reason' => $this->reason]));
        }

        if ($this->refundAmount > 0) {
            $mail->line(__('A refund of **:amount €** is being processed by the treasurer. No action is needed on your part.', [
                'amount' => number_format($this->refundAmount, 2),
            ]));
        }

        return $mail
            ->action(__('See my trainings'), url('/'))
            ->salutation(__('The club team'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
