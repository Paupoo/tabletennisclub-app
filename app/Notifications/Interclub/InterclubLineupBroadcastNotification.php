<?php

declare(strict_types=1);

namespace App\Notifications\Interclub;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;
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
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'interclub_id' => $this->interclub->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $interclub = $this->interclub;
        $interclub->loadMissing(['visitedTeam', 'visitingTeam', 'visitedTeam.club', 'visitingTeam.club', 'room']);

        $ourTeam = $interclub->visitedTeam?->club?->licence === config('app.club_licence')
            ? $interclub->visitedTeam
            : $interclub->visitingTeam;

        $isHome = $interclub->visitedTeam?->id === $ourTeam?->id;
        $opponentTeam = $isHome ? $interclub->visitingTeam : $interclub->visitedTeam;
        $opponent = trim(($opponentTeam?->club?->name ?? '') . ' ' . ($opponentTeam?->name ?? '')) ?: '—';

        $dateStr = $interclub->start_date_time->format('d/m/Y');
        $timeStr = $interclub->start_date_time->format('H:i');
        $teamName = $ourTeam?->name ?? '—';

        $lineupNames = $this->selectedPlayers
            ->map(fn (User $p) => $p->full_name)
            ->implode(', ');

        $mail = (new MailMessage)
            ->subject(__('Lineup confirmed — :team vs :opponent on :date', [
                'team' => $teamName,
                'opponent' => $opponent,
                'date' => $dateStr,
            ]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('The lineup for the **:team** match has been confirmed.', ['team' => $teamName]))
            ->line('---')
            ->line(__('**Date:** :date at :time', ['date' => $dateStr, 'time' => $timeStr]))
            ->line(__('**Opponent:** :opponent', ['opponent' => $opponent]))
            ->line(__('**Location:** :address', ['address' => $interclub->room?->address ?? $interclub->address ?? '—']))
            ->line('---')
            ->line(__('**Selected players:** :lineup', ['lineup' => $lineupNames ?: '—']));

        if (! empty($this->captainMessage)) {
            $mail->line('---')
                ->line(__('**Message from your captain:**'))
                ->line($this->captainMessage);
        }

        $mail->salutation(__('See you at the match!'));

        return $mail;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
