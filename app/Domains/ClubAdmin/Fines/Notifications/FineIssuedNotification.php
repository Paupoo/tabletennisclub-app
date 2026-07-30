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

        return (new MailMessage)
            ->subject(__('A fine has been issued'))
            ->markdown('mail.fine-issued', [
                'fine' => $this->fine,
                'member' => $this->fine->user,
                'payment' => $payment,
                'qrCode' => $payment ? (new GeneratePaymentQR)($payment) : null,
                'club' => $club,
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
