<?php

declare(strict_types=1);

namespace App\Support;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The mail channel, writing to each address of a notifiable separately.
 *
 * A member can be spoken for by several addresses — a teenager and both of
 * their parents, see {@see User::contactEmails()}.
 * Laravel's channel would put them all in one `To:` line and hand each
 * separated parent the other's address. This one sends the same notification
 * once per address instead, rebuilding the message each time so that a
 * notification may still address the one reader in front of it.
 *
 * A notification that returns a {@see Mailable} is addressed here too, which
 * is why such notifications leave `to()` alone.
 */
class PerRecipientMailChannel extends MailChannel
{
    /** The single address the message being built is for. */
    private ?string $currentRecipient = null;

    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification): ?SentMessage
    {
        $route = $notifiable->routeNotificationFor('mail', $notification);

        if (! is_array($route)) {
            return parent::send($notifiable, $notification);
        }

        $lastSent = null;

        foreach ($route as $key => $value) {
            $this->currentRecipient = is_string($key) ? $key : $value;

            try {
                $lastSent = $this->sendToCurrentRecipient($notifiable, $notification) ?? $lastSent;
            } finally {
                $this->currentRecipient = null;
            }
        }

        return $lastSent;
    }

    /**
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     * @param  MailMessage  $message
     * @return array<string, string>|mixed
     */
    protected function getRecipients($notifiable, $notification, $message)
    {
        if ($this->currentRecipient !== null) {
            return [$this->currentRecipient];
        }

        return parent::getRecipients($notifiable, $notification, $message);
    }

    /**
     * The parent's send, for the one address being written to. The message is
     * rebuilt for each address, so a notification may greet its reader.
     *
     * @param  mixed  $notifiable
     */
    private function sendToCurrentRecipient($notifiable, Notification $notification): ?SentMessage
    {
        if (! method_exists($notification, 'toMail')) {
            return null;
        }

        $message = $notification->toMail($notifiable);

        if ($message instanceof Mailable) {
            $message->to = [];

            return $message->to($this->currentRecipient)->send($this->mailer);
        }

        return $this->mailer->mailer($message->mailer ?? null)->send(
            $this->buildView($message),
            array_merge($message->data(), $this->additionalMessageData($notification)),
            $this->messageBuilder($notifiable, $notification, $message)
        );
    }
}
