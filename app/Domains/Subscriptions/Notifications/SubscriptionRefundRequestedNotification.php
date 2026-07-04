<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRefundRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
        public readonly Subscription $subscription,
        public readonly string $reason = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Remboursement demandé'),
            'body' => __('Consultez vos paiements en attente'),
            'url' => '#',
            'category' => 'payment',
            'icon' => 'o-credit-card',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $member = $this->subscription->user;
        $amount = number_format((float) $this->payment->amount_due, 2);
        $adminUrl = route('admin.users.edit', $member->id);

        $reasonLine = $this->reason !== ''
            ? $this->reason
            : __('The **:season** subscription of :member has been cancelled after money was collected.', [
                'member' => $member->full_name,
                'season' => $this->subscription->season->name,
            ]);

        $mail = (new MailMessage)
            ->subject(__('Refund to process — :name', ['name' => $member->full_name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line($reasonLine)
            ->line(__('**Amount to refund: :amount €**', ['amount' => $amount]));

        if ($member->iban) {
            $mail->line(__('**IBAN:** :iban', ['iban' => $member->iban]));
        } else {
            $mail->line(__('No IBAN on file — please contact the member to obtain their bank details.'));
        }

        return $mail
            ->action(__('View member profile'), $adminUrl)
            ->line(__('Please process this refund at your earliest convenience.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
