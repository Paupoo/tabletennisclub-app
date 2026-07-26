<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin changes the formula of an affiliation that has already been
 * invoiced. Not sent while the affiliation is still awaiting validation: nothing
 * has been announced to the member yet, so there is nothing to correct.
 */
class SubscriptionFormulaChangedNotification extends Notification
{
    use Queueable;

    /**
     * @param  float  $delta  positive when the member owes a complement, negative when the club owes a refund
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly float $delta = 0.0,
        public readonly ?string $paymentReference = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your affiliation formula has changed'),
            'body' => $this->subscription->is_competitive
                ? __('Your affiliation switched to competition')
                : __('Your affiliation switched to recreational'),
            'url' => route('admin.user.registration-management', $this->subscription->user_id),
            'category' => 'subscription',
            'icon' => 'o-identification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;

        $mail = (new MailMessage)
            ->subject(__('Affiliation :season — formula changed', ['season' => $season->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line($this->subscription->is_competitive
                ? __('Your affiliation for season **:season** is now a **competition** affiliation.', ['season' => $season->name])
                : __('Your affiliation for season **:season** is now a **recreational** affiliation.', ['season' => $season->name]));

        if ($this->delta > 0) {
            $mail->line(__('An extra **:amount €** remains to be paid.', [
                'amount' => number_format($this->delta, 2),
            ]));

            if ($this->paymentReference !== null) {
                $mail->line(__('Please quote the structured reference :reference with your transfer.', [
                    'reference' => $this->paymentReference,
                ]));
            }
        }

        if ($this->delta < 0) {
            $mail->line(__('A refund of **:amount €** will be issued to you.', [
                'amount' => number_format(abs($this->delta), 2),
            ]));

            if (! $notifiable->iban) {
                $mail->line(__('We have no bank account on file in your name: please give your IBAN to the secretariat to receive the refund.'));
            }
        }

        return $mail->line(__('For any question, simply reply to this email.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
