<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Models\ClubEvents\Meeting\Meeting;
use Illuminate\Support\Carbon;

class IcsGenerator
{
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
                    ->map(fn ($item, $i) => ($i + 1) . '. ' . $item->title)
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
