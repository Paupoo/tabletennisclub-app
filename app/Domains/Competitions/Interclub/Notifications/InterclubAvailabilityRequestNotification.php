<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterclubAvailabilityRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Interclub $interclub,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Availability request'),
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
        $opponent = $interclub->opponentTeam()?->fullName() ?? '—';
        $ourTeamName = $ourTeam?->fullName() ?? '—';
        $venue = $interclub->isHome() ? __('Home') : __('Away');
        $dateStr = $interclub->start_date_time->format('d/m/Y') . ' ' . __('at') . ' ' . $interclub->start_date_time->format('H:i');
        $address = $interclub->room?->address ?? $interclub->address ?? '—';
        $url = route('admin.interclubs.my-matches');

        return (new MailMessage)
            ->subject(__('Your availability needed — :team vs :opponent on :date', [
                'team' => $ourTeamName,
                'opponent' => $opponent,
                'date' => $interclub->start_date_time->format('d/m/Y'),
            ]))
            ->markdown('mail.interclub.availability-request', [
                'notifiable' => $notifiable,
                'ourTeamName' => $ourTeamName,
                'opponent' => $opponent,
                'dateStr' => $dateStr,
                'address' => $address,
                'venue' => $venue,
                'url' => $url,
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // Optional notification: honour the member's opt-out preference.
        if ($notifiable instanceof User && ! $notifiable->wantsNotification('availability_requests')) {
            return [];
        }

        return ['mail', 'database'];
    }
}
