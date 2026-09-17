<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Shared\Enums\MeetingFormatEnum;
use Illuminate\Support\Carbon;

class IcsGenerator
{
    /**
     * One interclub fixture, for a member's own calendar.
     *
     * Three hours rather than the two a meeting gets: a tie of sixteen singles
     * runs about that long, and the personal feed already settled on the same
     * figure — a member subscribed to the feed and downloading this file should
     * not end up with two blocks of different lengths on the same evening.
     *
     * Carries what the player needs on the night and nothing they would have to
     * come back for: which side of the fixture they are on, the captain's
     * meet-up instructions, the published lineup, and a link back to the page.
     *
     * The UID matches the personal feed's (`interclub-<id>@…`), so an agenda
     * holding both recognises one event rather than showing the evening twice.
     */
    public function forInterclub(Interclub $interclub, ?User $viewer = null): string
    {
        $interclub->loadMissing(['visitedTeam.club', 'visitingTeam.club', 'room']);

        $team = ($viewer ? $interclub->playerTeam($viewer) : null) ?? $interclub->ourTeam();
        $isHome = $team !== null && $interclub->visited_team_id === $team->id;
        $opponent = $isHome ? $interclub->visitingTeam : $interclub->visitedTeam;

        $start = $interclub->start_date_time;
        $end = $start->copy()->addHours(3);

        $lineup = $interclub->isLineupPublished()
            ? $interclub->getSelectedPlayers()->map(fn (User $player): string => $player->full_name)->implode(', ')
            : '';

        $description = collect([
            $isHome ? __('Home match') : __('Away match'),
            $interclub->captain_message,
            $lineup === '' ? null : __('Line-up: :players', ['players' => $lineup]),
            route('admin.interclubs.my-match', $interclub),
        ])->filter()->implode("\n");

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'ctt-ottignies-blocry';

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CTT Ottignies-Blocry//Interclub//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:interclub-' . $interclub->id . '@' . $host,
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;TZID=' . config('app.timezone') . ':' . $start->format('Ymd\THis'),
            'DTEND;TZID=' . config('app.timezone') . ':' . $end->format('Ymd\THis'),
            'SUMMARY:' . $this->escape(trim(($team?->fullName() ?? '') . ' vs ' . ($opponent?->fullName() ?? ''))),
            'LOCATION:' . $this->escape($interclub->room?->address ?? $interclub->address ?? ''),
            'DESCRIPTION:' . $this->escape($description),
            'URL:' . route('admin.interclubs.my-match', $interclub),
            'END:VEVENT',
            'END:VCALENDAR',
        ]) . "\r\n";
    }

    public function forMeeting(Meeting $meeting, bool $cancel = false): string
    {
        $start = $meeting->scheduled_at ?? now();
        $end = $meeting->ends_at ?? $start->copy()->addHours(2);

        $location = match ($meeting->format) {
            MeetingFormatEnum::PHYSICAL => $meeting->location ?? '',
            MeetingFormatEnum::VIRTUAL => $meeting->meeting_link ?? '',
        };

        $description = collect([
            $meeting->description,
            $meeting->format === MeetingFormatEnum::VIRTUAL && $meeting->meeting_link
                ? __('Meeting link: :link', ['link' => $meeting->meeting_link])
                : null,
            $meeting->agendaItems->isNotEmpty()
                ? __('Agenda:') . '\n' . $meeting->agendaItems
                    ->map(fn (MeetingAgendaItem $item, int $i): string => ($i + 1) . '. ' . $item->title)
                    ->implode('\n')
                : null,
        ])->filter()->implode('\n\n');

        $uid = 'meeting-' . $meeting->id . '@' . parse_url(config('app.url'), PHP_URL_HOST);
        $now = Carbon::now()->format('Ymd\THis\Z');
        $startFmt = $start->format('Ymd\THis');
        $endFmt = $end->format('Ymd\THis');
        $summary = $this->escape($meeting->title);
        $descEsc = $this->escape($description);
        $locEsc = $this->escape($location);
        $method = $cancel ? 'CANCEL' : 'REQUEST';
        $status = $cancel ? 'CANCELLED' : 'CONFIRMED';

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//TabletennisCLub//Meetings//FR',
            'CALSCALE:GREGORIAN',
            "METHOD:{$method}",
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$now}",
            "DTSTART:{$startFmt}",
            "DTEND:{$endFmt}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$descEsc}",
            "LOCATION:{$locEsc}",
            "STATUS:{$status}",
            'END:VEVENT',
            'END:VCALENDAR',
        ]) . "\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\\;', '\\,', '\\n'],
            $value
        );
    }
}
