<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\Competitions\Interclub\Models\Interclub;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * A duty, not a newsletter: no opt-out. It only goes out while a lineup is
 * waiting, so sending the lineups is how a captain stops receiving it.
 */
class CaptainLineupReminderNotification extends Notification
{
    use Queueable;

    /** @param Collection<int, Interclub> $interclubs */
    public function __construct(public readonly Collection $interclubs) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Lineups to send'),
            'body' => trans_choice(':count lineup is waiting to be sent to your team.|:count lineups are waiting to be sent to your team.', $this->interclubs->count(), ['count' => $this->interclubs->count()]),
            'url' => route('admin.interclubs.captain-selection'),
            'category' => 'interclub',
            'icon' => 'o-paper-airplane',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(trans_choice('Interclubs — :count lineup to send to your team|Interclubs — :count lineups to send to your team', $this->interclubs->count(), ['count' => $this->interclubs->count()]))
            ->markdown('mail.interclub.captain-reminder', [
                'notifiable' => $notifiable,
                'fixtures' => $this->interclubs->map(fn (Interclub $ic): array => [
                    'date' => $ic->start_date_time->format('d/m/Y'),
                    'team' => $ic->ourTeam()?->name ?? '—',
                    'opponent' => $ic->opponentTeam()?->fullName() ?? '—',
                    'days' => (int) now()->diffInDays($ic->start_date_time),
                    'saved' => $ic->awaitsSending(),
                ])->all(),
                'url' => route('admin.interclubs.captain-selection'),
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
