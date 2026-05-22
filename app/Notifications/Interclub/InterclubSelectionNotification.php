<?php

declare(strict_types=1);

namespace App\Notifications\Interclub;

use App\Models\ClubEvents\Interclub\Interclub;
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
            'interclub_id' => $this->interclub->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $interclub = $this->interclub;
        $interclub->loadMissing(['visitedTeam', 'visitingTeam', 'visitedTeam.club', 'visitingTeam.club']);

        $ourTeam = $interclub->visitedTeam?->club?->licence === config('app.club_licence')
            ? $interclub->visitedTeam
            : $interclub->visitingTeam;

        $opponent = $interclub->visitedTeam?->id === $ourTeam?->id
            ? ($interclub->visitingTeam?->name ?? '—')
            : ($interclub->visitedTeam?->name ?? '—');

        $dateStr = $interclub->start_date_time->format('d/m/Y');
        $timeStr = $interclub->start_date_time->format('H:i');
        $teamName = $ourTeam?->name ?? '—';

        $mail = (new MailMessage)
            ->subject(__('Interclub — You are selected for :team on :date', ['team' => $teamName, 'date' => $dateStr]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('You have been selected for the **:team** interclub match.', ['team' => $teamName]))
            ->line('---')
            ->line(__('**Date:** :date at :time', ['date' => $dateStr, 'time' => $timeStr]))
            ->line(__('**Opponent:** :opponent', ['opponent' => $opponent]))
            ->line(__('**Location:** :address', ['address' => $interclub->address ?? '—']));

        if (! empty($this->captainMessage)) {
            $mail->line('---')
                ->line(__('**Message from your captain:**'))
                ->line($this->captainMessage);
        }

        $mail->salutation(__('See you on the court!'));

        $ics = $this->buildIcs($interclub, $opponent, $teamName);
        $mail->attachData($ics, 'interclub.ics', ['mime' => 'text/calendar']);

        return $mail;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    private function buildIcs(Interclub $interclub, string $opponent, string $teamName): string
    {
        $tz = 'Europe/Brussels';
        $dtStart = 'DTSTART;TZID=' . $tz . ':' . $interclub->start_date_time->format('Ymd\THis');
        $dtEnd = 'DTEND;TZID=' . $tz . ':' . $interclub->start_date_time->addHours(3)->format('Ymd\THis');
        $stamp = now()->utc()->format('Ymd\THis\Z');
        $summary = $this->icalEscape($teamName . ' vs ' . $opponent);
        $location = $this->icalEscape($interclub->address ?? '');

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
