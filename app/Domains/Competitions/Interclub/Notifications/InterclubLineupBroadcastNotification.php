<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class InterclubLineupBroadcastNotification extends Notification
{
    use Queueable;

    /** @param Collection<int, User> $selectedPlayers */
    public function __construct(
        public readonly Interclub $interclub,
        public readonly Collection $selectedPlayers,
        public readonly string $captainMessage = '',
        public readonly bool $isUpdate = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Line-up published'),
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
        $category = $ourTeam?->league?->category;
        $opponent = $interclub->opponentTeam()?->fullName() ?? '—';
        $venue = $interclub->isHome() ? __('Home') : __('Away');
        $dateStr = $interclub->start_date_time->format('d/m/Y') . ' ' . __('at') . ' ' . $interclub->start_date_time->format('H:i');
        $address = $interclub->room?->address ?? $interclub->address ?? '—';

        $subject = $this->isUpdate
            ? __('Lineup updated — :team vs :opponent on :date', [
                'team' => $ourTeamName,
                'opponent' => $opponent,
                'date' => $interclub->start_date_time->format('d/m/Y'),
            ])
            : __('Lineup confirmed — :team vs :opponent on :date', [
                'team' => $ourTeamName,
                'opponent' => $opponent,
                'date' => $interclub->start_date_time->format('d/m/Y'),
            ]);

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.interclub.lineup', [
                'notifiable' => $notifiable,
                'ourTeamName' => $ourTeamName,
                'opponent' => $opponent,
                'dateStr' => $dateStr,
                'address' => $address,
                'venue' => $venue,
                'selectedPlayers' => $this->selectedPlayers,
                'category' => $category,
                'captainMessage' => $this->captainMessage,
                'isUpdate' => $this->isUpdate,
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // Optional notification: honour the member's opt-out preference.
        if ($notifiable instanceof User && ! $notifiable->wantsNotification('interclub_selections')) {
            return [];
        }

        return ['mail', 'database'];
    }
}
