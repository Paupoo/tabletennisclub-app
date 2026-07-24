<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
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
            'title' => __('Vous êtes sélectionné'),
            'body' => __('Consultez les détails du match'),
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
        $selectedPlayers = $interclub->getSelectedPlayers();

        $ics = $this->buildIcs($interclub, $opponent, $ourTeamName, $interclub->room?->address ?? $interclub->address ?? '');

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

    private function buildIcs(Interclub $interclub, string $opponent, string $teamName, string $address = ''): string
    {
        $tz = 'Europe/Brussels';
        $dtStart = 'DTSTART;TZID=' . $tz . ':' . $interclub->start_date_time->format('Ymd\THis');
        $dtEnd = 'DTEND;TZID=' . $tz . ':' . $interclub->start_date_time->addHours(3)->format('Ymd\THis');
        $stamp = now()->utc()->format('Ymd\THis\Z');
        $summary = $this->icalEscape($teamName . ' vs ' . $opponent);
        $location = $this->icalEscape($address);

        $properties = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CTT Ottignies Blocry//Interclub//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:interclub-' . $interclub->id . '@cttottigniesblocry.be',
            'DTSTAMP:' . $stamp,
            $dtStart,
            $dtEnd,
            'SUMMARY:' . $summary,
            'LOCATION:' . $location,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        $lines = array_map(fn (string $line) => $this->icalFold($line), $properties);

        return implode("\r\n", $lines) . "\r\n";
    }

    private function icalEscape(string $value): string
    {
        return str_replace(["\r\n", "\n", "\r", ',', ';', '\\'], ['\\n', '\\n', '\\n', '\\,', '\\;', '\\\\'], $value);
    }

    private function icalFold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        while (strlen($line) > 75) {
            $folded .= mb_substr($line, 0, 75) . "\r\n ";
            $line = mb_substr($line, 75);
        }

        return $folded . $line;
    }
}
