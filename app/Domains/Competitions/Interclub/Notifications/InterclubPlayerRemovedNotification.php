<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\Competitions\Interclub\Models\Interclub;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterclubPlayerRemovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Interclub $interclub,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('You are no longer selected'),
            'body' => __('See the match details'),
            'url' => route('admin.interclubs.my-matches'),
            'category' => 'interclub',
            'icon' => 'o-user-group',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $interclub = $this->interclub;
        $interclub->loadMissing(['visitedTeam.club', 'visitingTeam.club', 'room']);

        $ourTeam = $interclub->ourTeam();
        $ourTeamName = $ourTeam?->fullName() ?? '—';
        $opponent = $interclub->opponentTeam()?->fullName() ?? '—';
        $venue = $interclub->isHome() ? __('Home') : __('Away');
        $dateStr = $interclub->start_date_time->format('d/m/Y') . ' ' . __('at') . ' ' . $interclub->start_date_time->format('H:i');
        $address = $interclub->room?->address ?? $interclub->address ?? '—';

        return (new MailMessage)
            ->subject(__('Interclub — You are no longer selected for :team on :date', [
                'team' => $ourTeamName,
                'date' => $interclub->start_date_time->format('d/m/Y'),
            ]))
            ->markdown('mail.interclub.removed', [
                'notifiable' => $notifiable,
                'ourTeamName' => $ourTeamName,
                'opponent' => $opponent,
                'dateStr' => $dateStr,
                'address' => $address,
                'venue' => $venue,
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
