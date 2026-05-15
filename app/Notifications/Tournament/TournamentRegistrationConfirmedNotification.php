<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentRegistrationConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public bool $isWaitlisted = false,
        public int $waitlistPosition = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'waitlisted' => $this->isWaitlisted,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tournament = $this->tournament;
        $tournament->loadMissing('rooms');

        $matchType = $tournament->match_type === 'double' ? __('Doubles') : __('Singles');
        $sets = __(':n winning sets (Best of :total)', [
            'n' => $tournament->sets_to_win,
            'total' => ($tournament->sets_to_win * 2) - 1,
        ]);
        $handicap = $tournament->has_handicap_points ? __('Yes') : __('No');
        $location = $tournament->rooms->pluck('name')->join(', ') ?: '—';
        $dateStr = $tournament->start_date?->format('d/m/Y') ?? '—';
        $timeStr = $tournament->start_time ?? '—';

        if ($this->isWaitlisted) {
            $mail = (new MailMessage)
                ->subject(__(':tournament — Waitlist confirmation', ['tournament' => $tournament->name]))
                ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
                ->line(__('You have been added to the waiting list for **:tournament**.', ['tournament' => $tournament->name]))
                ->line(__('Your current position: **#:position**', ['position' => $this->waitlistPosition]))
                ->line(__('This position may move up as other players withdraw.'))
                ->line(__('If a spot opens up, you will receive an email to confirm your participation. You will have 48 hours to respond — after that, the spot will be offered to the next person on the list.'));
        } else {
            $mail = (new MailMessage)
                ->subject(__(':tournament — Registration confirmed', ['tournament' => $tournament->name]))
                ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
                ->line(__('Your registration for **:tournament** is confirmed!', ['tournament' => $tournament->name]));
        }

        $mail
            ->line('---')
            ->line(__('**Date:** :date at :time', ['date' => $dateStr, 'time' => $timeStr]))
            ->line(__('**Location:** :rooms', ['rooms' => $location]))
            ->line(__('**Format:** :type — :sets', ['type' => $matchType, 'sets' => $sets]))
            ->line(__('**Handicap points:** :handicap', ['handicap' => $handicap]));

        if ($tournament->isPaid()) {
            $mail->line(__('**Entry fee:** :price €', ['price' => number_format((float) $tournament->price, 2)]));
            $mail->line(__('Payment instructions have been sent separately.'));
        }

        if ($tournament->registration_deadline) {
            $mail->line(__('**Registration deadline:** :date', [
                'date' => $tournament->registration_deadline->format('d/m/Y'),
            ]));
        }

        $mail->salutation(__('See you on the court!'));

        if ($tournament->start_date && ! $this->isWaitlisted) {
            $ics = $this->buildIcs($tournament, $location);
            $mail->attachData($ics, 'tournament.ics', ['mime' => 'text/calendar']);
        }

        return $mail;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    private function buildIcs(Tournament $tournament, string $location): string
    {
        $start = $tournament->start_date->format('Ymd');
        $startTime = str_replace(':', '', $tournament->start_time ?? '000000');
        $dtStart = $start . ($tournament->start_time ? 'T' . $startTime . '00' : '');
        $dtEnd = $tournament->end_date
            ? $tournament->end_date->format('Ymd\THis')
            : $tournament->start_date->format('Ymd\THis');
        $uid = 'tournament-' . $tournament->id . '@tabletennisclub';
        $name = str_replace(["\r", "\n", ',', ';'], [' ', ' ', '\,', '\;'], $tournament->name);
        $loc = str_replace(["\r", "\n", ',', ';'], [' ', ' ', '\,', '\;'], $location);

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//TableTennisClub//Tournament//EN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'SUMMARY:' . $name,
            'DTSTART:' . $dtStart,
            'DTEND:' . $dtEnd,
            'LOCATION:' . $loc,
            'END:VEVENT',
            'END:VCALENDAR',
        ]) . "\r\n";
    }
}
