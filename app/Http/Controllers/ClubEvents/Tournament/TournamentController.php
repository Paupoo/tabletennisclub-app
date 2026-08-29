<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Tournament;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * The tournament endpoints a visitor reaches from an e-mail, outside the admin
 * UI: the signed join and leave-waitlist links, the page they land on, and the
 * calendar file.
 *
 * Everything else a tournament needs lives in the Livewire wizard and live
 * center. Thirty-one methods covering pools, matches, tables and the CRUD were
 * superseded by them and stayed here unrouted until 2026-08-01.
 */
class TournamentController extends Controller
{
    public function __construct(
        private readonly TournamentService $tournamentService,
    ) {}

    public function downloadIcal(Tournament $tournament): Response
    {
        $start = $tournament->start_date->copy();

        if ($tournament->start_time) {
            [$h, $m] = explode(':', $tournament->start_time);
            $start->setTime((int) $h, (int) $m);
        }

        $end = $start->copy()->addMinutes($tournament->duration_minutes ?: 180);

        // Store times as entered (no UTC conversion) and declare the timezone
        // explicitly with TZID so every calendar app displays the exact time encoded.
        $tz = 'Europe/Brussels';
        $dtStart = 'DTSTART;TZID=' . $tz . ':' . $start->format('Ymd\THis');
        $dtEnd = 'DTEND;TZID=' . $tz . ':' . $end->format('Ymd\THis');
        $stamp = now()->utc()->format('Ymd\THis\Z');

        $properties = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CTT Ottignies Blocry//Tournament//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:tournament-' . $tournament->id . '@cttottigniesblocry.be',
            'DTSTAMP:' . $stamp,
            $dtStart,
            $dtEnd,
            'SUMMARY:' . $this->icalEscape($tournament->name),
            'DESCRIPTION:' . $this->icalEscape(__('Table tennis tournament') . ($tournament->description ? ' — ' . $tournament->description : '')),
            'LOCATION:' . $this->icalEscape($tournament->location ?? ''),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        // RFC 5545 §3.1: fold lines longer than 75 octets with CRLF + SPACE.
        $lines = array_map($this->icalFold(...), $properties);

        $slug = Str::slug($tournament->name);

        return response(implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$slug}.ics\"",
        ]);
    }

    public function leaveWaitlistViaEmail(Tournament $tournament, User $user): RedirectResponse
    {
        $existingPivot = $tournament->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('registration_status', ['waiting', 'spot_offered'])
            ->first()?->pivot;

        if ($existingPivot) {
            $this->tournamentService->cancelRegistration($tournament, $user);
        }

        return redirect()
            ->route('tournament.registration.confirmed', $tournament)
            ->with('registration_status', 'left_waitlist');
    }

    public function registerViaEmail(Tournament $tournament, User $user): RedirectResponse
    {
        // Tournament::users() declares ->using(TournamentRegistration::class), so
        // the pivot is that model; static analysis cannot see it through ->pivot.
        /** @var TournamentRegistration|null $existingPivot */
        $existingPivot = $tournament->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'waiting', 'spot_offered'])
            ->first()?->pivot;

        if ($existingPivot?->registration_status === 'waiting') {
            return redirect()
                ->route('tournament.registration.confirmed', $tournament)
                ->with('registration_status', 'waiting')
                ->with('waitlist_position', $existingPivot->waitlist_position)
                ->with('already_on_list', true);
        }

        // spot_offered: user was promoted from waitlist, this is their confirmation click.
        if ($existingPivot?->registration_status === 'spot_offered') {
            DB::table('tournament_user')
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->update(['registration_status' => 'registered', 'confirmation_deadline' => null]);

            return redirect()
                ->route('tournament.registration.confirmed', $tournament)
                ->with('registration_status', 'registered');
        }

        if (in_array($existingPivot?->registration_status, ['registered', 'confirmed'], true)) {
            return redirect()
                ->route('tournament.registration.confirmed', $tournament)
                ->with('registration_status', 'registered')
                ->with('already_on_list', true);
        }

        if (! $tournament->registrationsAreOpen()) {
            return redirect()
                ->route('tournament.registration.confirmed', $tournament)
                ->with('error', 'Registrations are closed for this tournament.');
        }

        try {
            $this->tournamentService->registerUser($tournament, $user);
        } catch (Throwable $th) {
            return redirect()
                ->route('tournament.registration.confirmed', $tournament)
                ->with('error', $th->getMessage());
        }

        /** @var TournamentRegistration|null $pivot */
        $pivot = $tournament->users()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        return redirect()
            ->route('tournament.registration.confirmed', $tournament)
            ->with('registration_status', $pivot?->registration_status ?? 'registered')
            ->with('waitlist_position', $pivot?->waitlist_position);
    }

    public function registrationConfirmed(Tournament $tournament): View
    {
        $registrationStatus = session('registration_status');
        $waitlistPosition = session('waitlist_position');

        return view('public.tournament.registration-confirmed', compact(
            'tournament',
            'registrationStatus',
            'waitlistPosition',
        ));
    }

    /**
     * Escape a text value for RFC 5545 §3.3.11.
     *
     * The backslash goes first on purpose: str_replace applies each pair to the
     * result of the previous one, so escaping it last would double every
     * backslash the comma, semicolon and newline rules had just introduced. A
     * name holding a comma came out as `\\,` — an escaped backslash followed by
     * a bare comma, which calendars read as a value separator.
     */
    private function icalEscape(string $value): string
    {
        return str_replace(['\\', "\r\n", "\n", "\r", ',', ';'], ['\\\\', '\\n', '\\n', '\\n', '\\,', '\\;'], $value);
    }

    /**
     * Fold a content line to 75 octets, per RFC 5545 §3.1.
     *
     * The limit is expressed in octets, not characters, so the cut uses byte
     * functions throughout — measuring with strlen() while slicing with
     * mb_substr() let any accented name overflow the limit. A multi-byte
     * character must not be split across the fold, hence the backtrack.
     */
    private function icalFold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';

        while (strlen($line) > 75) {
            $take = 75;

            // Never cut inside a UTF-8 sequence: 0b10xxxxxx marks a continuation
            // byte, so step back until the next byte starts a character.
            while ($take > 0 && (ord($line[$take]) & 0xC0) === 0x80) {
                $take--;
            }

            $folded .= substr($line, 0, $take) . "\r\n ";
            $line = substr($line, $take);
        }

        return $folded . $line;
    }
}
