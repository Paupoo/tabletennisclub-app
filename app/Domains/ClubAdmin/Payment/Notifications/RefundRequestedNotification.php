<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Notifications;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Payment $payment,
        public User $member,
        public Tournament $tournament,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Refund requested'),
            'body' => __('See your pending payments'),
            'url' => '#',
            'category' => 'payment',
            'icon' => 'o-credit-card',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->payment->amount_due, 2);
        $adminUrl = route('admin.users.edit', $this->member->id);

        $mail = (new MailMessage)
            ->subject(__('Refund to process — :name', ['name' => $this->member->full_name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__(':member has been unregistered from **:tournament** after having paid their entry fee.', [
                'member' => $this->member->full_name,
                'tournament' => $this->tournament->name,
            ]))
            ->line(__('**Amount to refund: :amount €**', ['amount' => $amount]));

        if ($this->member->iban) {
            $mail->line(__('**IBAN:** :iban', ['iban' => $this->member->iban_formatted]));
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
