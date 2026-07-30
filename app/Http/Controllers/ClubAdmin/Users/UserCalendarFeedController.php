<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Users;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Response;

class UserCalendarFeedController extends Controller
{
    /**
     * Personal ICS feed of the member's upcoming club activities, meant to be
     * subscribed to from Google Calendar / Apple Calendar. The route is signed
     * (permanent signature, no session): the URL itself is the secret, so it
     * can be polled by calendar providers without authentication.
     */
    public function __invoke(User $user, UserCalendarService $calendar): Response
    {
        $events = $calendar->eventsFor($user);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CTT Ottignies-Blocry//Espace membre//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape(__('CTT Ottignies-Blocry — :name', ['name' => $user->first_name])),
            'X-WR-TIMEZONE:' . config('app.timezone'),
        ];

        foreach ($events as $event) {
            $start = Carbon::parse($event['startDateTime'], config('app.timezone'));

            // Trainings expose their real end time; other events get a
            // reasonable default duration so agendas can display a block.
            $end = isset($event['endTime']) && $event['endTime']
                ? $start->copy()->setTimeFromTimeString($event['endTime'])
                : $start->copy()->addHours(2);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $start->copy()->addHours(2);
            }

            $location = $event['address'] ?? $event['room'] ?? $event['location'] ?? null;
            if ($location === '—') {
                $location = null;
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . hash('sha256', $event['type'] . '|' . $event['title'] . '|' . $event['startDateTime']) . '@ctt-ottignies-blocry';
            $lines[] = 'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $start->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $end->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $this->escape($event['title']);
            $lines[] = 'CATEGORIES:' . $this->escape(strtoupper($event['type']));

            if ($location) {
                $lines[] = 'LOCATION:' . $this->escape($location);
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ctt-ottignies-blocry.ics"',
        ]);
    }

    /**
     * Escape text per RFC 5545 (commas, semicolons, backslashes, newlines).
     */
    private function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $text
        );
    }
}
