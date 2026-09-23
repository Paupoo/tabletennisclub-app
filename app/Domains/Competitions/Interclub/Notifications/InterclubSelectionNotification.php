<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Services\IcsGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterclubSelectionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Interclub $interclub,
        public readonly string $captainMessage = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('You are selected'),
            'body' => __('See the match details'),
            'url' => route('admin.interclubs.my-match', $this->interclub),
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
        $selectedPlayers = $interclub->getSelectedPlayers();

        $ics = app(IcsGenerator::class)->forInterclub($interclub, $notifiable instanceof User ? $notifiable : null);

        return (new MailMessage)
            ->subject(__('Interclub — You are selected for :team on :date', [
                'team' => $ourTeamName,
                'date' => $interclub->start_date_time->format('d/m/Y'),
            ]))
            ->markdown('mail.interclub.selection', [
                'notifiable' => $notifiable,
                'ourTeamName' => $ourTeamName,
                'opponent' => $opponent,
                'dateStr' => $dateStr,
                'address' => $address,
                'venue' => $venue,
                'selectedPlayers' => $selectedPlayers,
                'category' => $category,
                'captainMessage' => $this->captainMessage,
                // Jouer à 3 : la déclaration du capitaine, lue au moment de l'envoi.
                'shortHanded' => $interclub->isShortHanded()
                    ? ['playing' => $interclub->getSelectedPlayers()->count(), 'max' => $interclub->total_players]
                    : null,
                'url' => route('admin.interclubs.my-match', $interclub),
            ])
            ->attachData($ics, 'interclub.ics', ['mime' => 'text/calendar']);
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
