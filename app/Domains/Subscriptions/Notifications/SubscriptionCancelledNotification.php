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
            'title' => __('Affiliation cancelled'),
            'body' => __('Your affiliation has been cancelled'),
            'url' => '#',
            'category' => 'subscription',
            'icon' => 'o-identification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;

        $mail = (new MailMessage)
            ->subject(__('Affiliation :season — cancellation confirmed', ['season' => $season->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Your affiliation for season **:season** has been cancelled.', ['season' => $season->name]));

        if ($this->refundAmount > 0) {
            $mail->line(__('A refund of **:amount €** will be issued to you.', [
                'amount' => number_format($this->refundAmount, 2),
            ]));

            if ($notifiable->iban) {
                $mail->line(__('The refund will be paid to account :iban.', ['iban' => $notifiable->iban]));
            } else {
                $mail->line(__('We have no bank account on file in your name: please give your IBAN to the secretariat to receive the refund.'));
            }
        }

        if (! empty($this->message)) {
            $mail->line('---')
                ->line(__('**Message from the secretariat:**'))
                ->line($this->message);
        }

        return $mail
            ->line('---')
            ->line(__('Feel free to contact the secretariat with any question, or to submit a new request.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
