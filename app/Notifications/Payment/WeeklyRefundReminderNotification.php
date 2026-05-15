<?php

declare(strict_types=1);

namespace App\Notifications\Payment;

use App\Models\ClubAdmin\Payment\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class WeeklyRefundReminderNotification extends Notification
{
    use Queueable;

    /** @param Collection<int, Payment> $payments */
    public function __construct(public Collection $payments) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Weekly reminder — :count refund(s) pending', ['count' => $this->payments->count()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('The following :count payment(s) are awaiting refund:', ['count' => $this->payments->count()]));

        foreach ($this->payments as $payment) {
            $user = $payment->payable?->user;
            $tournament = $payment->payable?->tournament;

            if (! $user) {
                continue;
            }

            $amount = number_format((float) $payment->amount_due, 2);
            $line = "• **{$user->full_name}** — {$amount} €";

            if ($tournament) {
                $line .= " ({$tournament->name})";
            }

            if ($user->iban) {
                $line .= " — IBAN: {$user->iban}";
            } else {
                $line .= ' — ' . __('no IBAN on file');
            }

            $mail->line($line);
        }

        return $mail
            ->action(__('View pending refunds'), route('admin.payments'))
            ->line(__('Please process these refunds or contact the members who have no IBAN on file.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
