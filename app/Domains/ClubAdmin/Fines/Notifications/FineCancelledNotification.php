<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Fines\Notifications;

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\Competitions\Interclub\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class FineCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Fine $fine) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('A fine has been cancelled'),
            'body' => $this->fine->reason->label(),
            'url' => route('admin.user.payments', $this->fine->user_id),
            'category' => 'payment',
            'icon' => 'o-check-circle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('A fine has been cancelled'))
            ->markdown('mail.fine-cancelled', [
                'fine' => $this->fine,
                'member' => $this->fine->user,
                'club' => Club::ourClub()->first(),
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
