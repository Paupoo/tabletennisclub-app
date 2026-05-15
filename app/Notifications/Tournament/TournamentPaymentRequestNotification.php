<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Mail\TournamentPaymentRequestMail;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubEvents\Tournament\Tournament;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TournamentPaymentRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public Payment $payment,
        public Carbon $deadline,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): TournamentPaymentRequestMail
    {
        return (new TournamentPaymentRequestMail($this->tournament, $this->payment, $this->deadline))
            ->to($notifiable->email, $notifiable->full_name);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
