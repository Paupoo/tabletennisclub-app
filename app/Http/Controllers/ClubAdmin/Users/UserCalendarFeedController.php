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
            $end = $this->endOf($event, $start);
            $modified = $this->modifiedAt($event);

            $location = $event['address'] ?? $event['room'] ?? $event['location'] ?? null;
            if ($location === '—') {
                $location = null;
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event['type'] . '-' . ($event['sourceId'] ?? 0) . '@ctt-ottignies-blocry';
            // SEQUENCE must grow on every edit for an agenda to accept the new
            // version; the modification timestamp is monotonic by construction.
            $lines[] = 'SEQUENCE:' . $modified->getTimestamp();
            $lines[] = 'DTSTAMP:' . $modified->utc()->format('Ymd\THis\Z');
            $lines[] = 'LAST-MODIFIED:' . $modified->utc()->format('Ymd\THis\Z');
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

        // No Content-Disposition: agendas subscribe to this URL, they do not
        // download it, and `attachment` makes some clients treat it as a file.
        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
        ]);
    }

    /**
     * End of the block the subscriber sees.
     *
     * A source that knows its end wins. Interclub matches have no end column —
     * a tie of sixteen matches runs about three hours, and a two-hour block
     * would show the member free while they are still playing.
     *
     * @param  array<string, mixed>  $event
     */
    private function endOf(array $event, Carbon $start): Carbon
    {
        $end = ! empty($event['endDateTime'])
            ? Carbon::parse($event['endDateTime'], config('app.timezone'))
            : $start->copy()->addHours($event['type'] === 'interclub' ? 3 : 2);

        return $end->greaterThan($start) ? $end : $start->copy()->addHours(2);
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

    /**
     * When the event last changed, for SEQUENCE and LAST-MODIFIED.
     *
     * Deliberately not `now()`: a timestamp that moves on every read makes the
     * whole feed look modified at each poll, and rules out any future ETag.
     *
     * @param  array<string, mixed>  $event
     */
    private function modifiedAt(array $event): Carbon
    {
        return ! empty($event['updatedAt'])
            ? Carbon::parse($event['updatedAt'], config('app.timezone'))
            : Carbon::parse($event['startDateTime'], config('app.timezone'));
    }
}
