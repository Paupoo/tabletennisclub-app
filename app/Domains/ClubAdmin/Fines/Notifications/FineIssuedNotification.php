<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Fines\Notifications;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\Competitions\Interclub\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class FineIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Fine $fine) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('A fine has been issued'),
            'body' => $this->fine->reason->label(),
            'url' => route('admin.user.payments', $this->fine->user_id),
            'category' => 'payment',
            'icon' => 'o-exclamation-triangle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->fine->payment;
        $club = Club::ourClub()->first();

        $mail = (new MailMessage)
            ->subject(__('A fine has been issued'))
            ->markdown('mail.fine-issued', [
                'fine' => $this->fine,
                'member' => $this->fine->user,
                'payment' => $payment,
                'club' => $club,
            ]);

        // Attached by name, and referenced as `cid:qr-paiement.png` in the view:
        // Gmail drops a `data:` source from an <img>, and embedding from the view
        // would attach a second copy, since a notification renders its text part
        // through the same Blade without the guard a mailable gets.
        if ($payment) {
            $mail->attachData(
                (new GeneratePaymentQR)->png($payment),
                'qr-paiement.png',
                ['mime' => 'image/png'],
            );
        }

        return $mail;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
