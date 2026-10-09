<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExternalParticipants\Notifications;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Money to hand back to a non-member, for the treasurers.
 *
 * The club holds no account number for them: the mail says so, and gives the
 * address to ask it from.
 */
class ExternalRefundRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
        public readonly ExternalRegistration $registration,
        public readonly string $reason = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Refund requested'),
            'body' => __('See your pending payments'),
            'url' => route('admin.treasury.payments'),
            'category' => 'payment',
            'icon' => 'o-credit-card',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Refund to process — :name', ['name' => $this->registration->displayName()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line($this->reason)
            ->line(__('**Amount to refund: :amount €**', ['amount' => number_format((float) $this->payment->amount_due, 2)]))
            ->line(__('No IBAN on file for this external participant: ask them at :email.', ['email' => $this->registration->email]))
            ->action(__('Open the payments'), route('admin.treasury.payments'))
            ->line(__('Please process this refund at your earliest convenience.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
