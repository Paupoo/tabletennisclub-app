<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExternalParticipants\Notifications;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a non-member that the club called their stage off.
 *
 * Mail only, sent on demand to the address encoded with the registration:
 * there is no account to hold a notification, nor a page to link to. The club
 * holds no account number either, so a refund is announced with the treasurer
 * coming back to ask for one.
 */
class ExternalCampCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ExternalRegistration $registration,
        public readonly ?string $reason = null,
        public readonly float $refundAmount = 0.0,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $camp = $this->registration->registrable;

        $mail = (new MailMessage)
            ->subject(__('Training camp cancelled — :pack', ['pack' => $camp->name]))
            ->greeting(__('Hello :name!', ['name' => $this->registration->greetingName()]))
            ->line(__('The club has had to cancel **:pack**. The registration of :participant is cancelled.', [
                'pack' => $camp->name,
                'participant' => $this->registration->displayName(),
            ]));

        if ($this->reason) {
            $mail->line(__('Reason given: :reason', ['reason' => $this->reason]));
        }

        if ($this->refundAmount > 0) {
            $mail->line(__('A refund of **:amount €** will be made: the treasurer will contact you for your bank account number.', [
                'amount' => number_format($this->refundAmount, 2),
            ]));
        }

        return $mail->salutation(__('The club team'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
