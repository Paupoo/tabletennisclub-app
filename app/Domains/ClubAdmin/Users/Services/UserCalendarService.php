<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Trainings\Models\Training;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Aggregates every upcoming club activity relevant to a member (tournaments,
 * training sessions, meetings, interclub matches) into one normalized event
 * list. Feeds both the "My calendar" page and the personal ICS feed.
 */
class UserCalendarService
{
    /**
     * @param  string[]  $categories  empty = all categories
     * @param  CarbonInterface|null  $from  defaults to now (upcoming events only, e.g. ICS feed)
     * @param  CarbonInterface|null  $to  null = unbounded
     * @return Collection<int, array<string, mixed>> flat list sorted by start datetime
     */
    public function eventsFor(User $user, bool $showAllEvents = false, array $categories = [], ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $from ??= Carbon::now();

        $events = collect();

        if ($this->wants('tournament', $categories)) {
            $events = $events->merge($this->tournaments($user, $showAllEvents, $from, $to));
        }

        if ($this->wants('training', $categories)) {
            $events = $events->merge($this->trainingSessions($user, $showAllEvents, $from, $to));
        }

        if ($this->wants('meeting', $categories)) {
            $events = $events->merge($this->meetings($user, $showAllEvents, $from, $to));
        }

        if ($this->wants('interclub', $categories)) {
            $events = $events->merge($this->interclubs($user, $showAllEvents, $from, $to));
        }

        return $events->sortBy('startDateTime')->values();
    }

    /**
     * @param  int[]  $ourTeamIds
     * @param  int[]  $userTeamIds
     * @return array<string, mixed>
     */
    private function formatInterclub(Interclub $ic, array $ourTeamIds, array $userTeamIds): array
    {
        $isHome = in_array($ic->visited_team_id, $ourTeamIds);
        $ourTeam = $isHome ? $ic->visitedTeam : $ic->visitingTeam;
        $opponentTeam = $isHome ? $ic->visitingTeam : $ic->visitedTeam;
        $opponent = trim(($opponentTeam?->club?->name ?? '') . ' ' . ($opponentTeam?->name ?? '')) ?: '—';

        $pivot = $ic->users->first()?->registration;
        $availability = $pivot?->availability ? InterclubAvailability::from($pivot->availability) : null;

        return [
            'startDateTime' => $ic->start_date_time->format('Y-m-d H:i:s'),
            'title' => ($ourTeam?->name ?? '') . ' vs ' . $opponent,
            'type' => 'interclub',
            'isHome' => $isHome,
            'opponent' => $opponent,
            'teamName' => $ourTeam?->name ?? '—',
            'division' => $ic->league?->division ?? '',
            'address' => $ic->address ?? '—',
            'isUserInTeam' => in_array($ourTeam?->id, $userTeamIds),
            'availability' => $availability,
            'isSelected' => (bool) $pivot?->is_selected,
            'registrationStatus' => null,
            'monthKey' => $ic->start_date_time->translatedFormat('F Y'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function interclubs(User $user, bool $showAllEvents, CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $season = Season::where('is_active', true)->first();

        if (! $season) {
            return collect();
        }

        $ourTeamIds = Team::inClub()->where('season_id', $season->id)->pluck('id');

        if ($ourTeamIds->isEmpty()) {
            return collect();
        }

        $userTeamIds = $user->teams()->pluck('teams.id')->toArray();

        // In "My events" mode, only show matches for the user's own teams
        $filterTeamIds = $showAllEvents ? $ourTeamIds->toArray() : $userTeamIds;

        if (empty($filterTeamIds)) {
            return collect();
        }

        return Interclub::with([
            'visitedTeam.club',
            'visitingTeam.club',
            'league',
            'users' => fn ($q) => $q->where('users.id', $user->id),
        ])
            ->withoutByes()
            ->where('season_id', $season->id)
            ->where(fn ($q) => $q->whereIn('visited_team_id', $filterTeamIds)->orWhereIn('visiting_team_id', $filterTeamIds))
            ->where('start_date_time', '>=', $from)
            ->when($to, fn ($q) => $q->where('start_date_time', '<=', $to))
            ->orderBy('start_date_time')
            ->get()
            ->map(fn (Interclub $ic): array => $this->formatInterclub($ic, $ourTeamIds->toArray(), $userTeamIds));
    }

    /**
     * One calendar row for a meeting.
     *
     * Extracted for the same reason as {@see self::tournamentRow()}: Collection's
     * value template is invariant, so an inferred array shape cannot satisfy
     * `Collection<int, array<string, mixed>>` from inside a closure.
     *
     * @return array<string, mixed>
     */
    private function meetingRow(Meeting $meeting): array
    {
        return [
            'startDateTime' => $meeting->scheduled_at->format('Y-m-d H:i:s'),
            'title' => $meeting->title,
            'type' => 'meeting',
            'meetingId' => $meeting->id,
            'format' => $meeting->format->value,
            'location' => $meeting->location,
            'meetingLink' => $meeting->meeting_link,
            'registrationStatus' => $meeting->users->first()?->registration?->status?->value,
            'monthKey' => $meeting->scheduled_at->translatedFormat('F Y'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function meetings(User $user, bool $showAllEvents, CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $meetingsQuery = Meeting::whereIn('status', [MeetingStatusEnum::CONFIRMED->value])
            ->where('scheduled_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('scheduled_at', '<=', $to));

        if (! $showAllEvents) {
            $meetingsQuery->whereHas('users', fn ($q) => $q->where('meeting_user.user_id', $user->id));
        }

        return $meetingsQuery
            ->with(['users' => fn ($q) => $q->where('users.id', $user->id)])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Meeting $m): array => $this->meetingRow($m));
    }

    /**
     * One calendar row for a tournament, and the member's registration on it.
     *
     * Extracted so the row keeps the loose contract the calendar is built on:
     * Collection's value template is invariant, so an inferred array shape —
     * however accurate — cannot satisfy `Collection<int, array<string, mixed>>`
     * from inside a closure.
     *
     * @return array<string, mixed>
     */
    private function tournamentRow(Tournament $tournament, ?TournamentRegistration $registration): array
    {
        return [
            'startDateTime' => $tournament->startsAt()?->format('Y-m-d H:i:s'),
            'endDate' => $tournament->end_date?->format('Y-m-d'),
            'title' => $tournament->name,
            'type' => 'tournament',
            'tournamentId' => $tournament->id,
            'registrationStatus' => $registration?->registration_status,
            'waitlistPosition' => $registration?->waitlist_position,
            'confirmDeadline' => $registration?->confirmation_deadline?->format('Y-m-d H:i:s'),
            'monthKey' => $tournament->start_date->translatedFormat('F Y'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tournaments(User $user, bool $showAllEvents, CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        // A tournament overlaps the window as soon as it hasn't ended before $from
        // (multi-day tournaments keep showing while ongoing).
        $tournamentsQuery = Tournament::onTheCalendar()
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$from])
            ->when($to, fn ($q) => $q->where('start_date', '<=', $to));

        if (! $showAllEvents) {
            $tournamentsQuery->whereHas('users', fn ($q) => $q
                ->where('tournament_user.user_id', $user->id)
                ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
            );
        }

        $tournaments = $tournamentsQuery->orderBy('start_date')->get();

        /*
         * The member's own registration row, read through the pivot model rather
         * than through `$tournament->users->first()->pivot`. Same three columns,
         * but typed — and looked up once per tournament instead of three times,
         * which is what the previous shape did.
         */
        $registrations = TournamentRegistration::where('user_id', $user->id)
            ->whereIn('tournament_id', $tournaments->pluck('id'))
            ->get()
            ->keyBy('tournament_id');

        return $tournaments->map(
            fn (Tournament $t): array => $this->tournamentRow($t, $registrations->get($t->id)),
        );
    }

    /**
     * One calendar row for a training session.
     *
     * `$packStatuses` is null when the member is browsing the club's whole
     * calendar: the enrolment columns are then not theirs to show. It is an
     * array — possibly empty — when the row belongs to their own calendar, which
     * is why emptiness cannot stand in for the distinction: a coach with no pack
     * of their own still gets their own calendar.
     *
     * Extracted for the same reason as {@see self::tournamentRow()}, and it
     * happens to remove the copy of this row that the two branches each kept.
     *
     * @param  array<int, array<string, mixed>>|null  $packStatuses
     * @return array<string, mixed>
     */
    private function trainingRow(Training $session, ?array $packStatuses): array
    {
        $row = [
            'startDateTime' => $session->start->format('Y-m-d H:i:s'),
            'endTime' => $session->end?->format('H:i'),
            'title' => $session->trainingPack?->name ?? __('Training'),
            'type' => 'training',
            'room' => $session->room?->name,
            'level' => $session->trainingPack?->level?->label,
            'coach' => $session->trainer
                ? trim($session->trainer->first_name . ' ' . $session->trainer->last_name)
                : null,
            'registrationStatus' => null,
        ];

        // `monthKey` reste la dernière clé des deux formes, comme avant
        // l'extraction : les colonnes d'inscription s'insèrent avant elle.
        $monthKey = ['monthKey' => $session->start->translatedFormat('F Y')];

        if ($packStatuses === null) {
            return [...$row, ...$monthKey];
        }

        $enrolment = $packStatuses[$session->training_pack_id] ?? [];

        return [
            ...$row,
            'packId' => $session->training_pack_id,
            'packStatus' => $enrolment['status'] ?? 'enrolled',
            'confirmDeadline' => $enrolment['deadline'] ?? null,
            'packWaitlistPosition' => $enrolment['waitlist_position'] ?? null,
            ...$monthKey,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function trainingSessions(User $user, bool $showAllEvents, CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $season = Season::where('is_active', true)->first();

        if (! $season) {
            return collect();
        }

        if ($showAllEvents) {
            return Training::with(['trainingPack', 'room', 'trainer'])
                ->where('status', 'scheduled')
                ->where('start', '>=', $from)
                ->when($to, fn ($q) => $q->where('start', '<=', $to))
                ->orderBy('start')
                ->get()
                ->map(fn (Training $s): array => $this->trainingRow($s, null));
        }

        // Sessions the user is personally involved in.
        // Build a pack → pivot status map so we can show the right badge.
        $subs = $user->subscriptions()
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['cancelled'])
            ->with('trainingPacks')
            ->get();

        $packStatusMap = [];
        foreach ($subs as $sub) {
            foreach ($sub->trainingPacks as $pack) {
                // Un pack quitté garde sa ligne pour la facturation, mais ses
                // séances n'ont plus rien à faire dans l'agenda du membre.
                if ($pack->pivot->status === 'left') {
                    continue;
                }

                $packStatusMap[$pack->id] = [
                    'status' => $pack->pivot->status,
                    'deadline' => $pack->pivot->confirmation_deadline,
                    'waitlist_position' => $pack->pivot->waitlist_position,
                ];
            }
        }

        $enrolledPackIds = collect($packStatusMap)->keys();

        $sessionIds = collect();

        if ($enrolledPackIds->isNotEmpty()) {
            $sessionIds = $sessionIds->merge(
                Training::whereIn('training_pack_id', $enrolledPackIds)
                    ->where('status', 'scheduled')
                    ->where('start', '>=', $from)
                    ->when($to, fn ($q) => $q->where('start', '<=', $to))
                    ->pluck('id')
            );
        }

        $sessionIds = $sessionIds->merge(
            Training::where('trainer_id', $user->id)
                ->where('status', 'scheduled')
                ->where('start', '>=', $from)
                ->when($to, fn ($q) => $q->where('start', '<=', $to))
                ->pluck('id')
        );

        $sessionIds = $sessionIds->merge(
            $user->trainings()
                ->where('trainings.status', 'scheduled')
                ->where('trainings.start', '>=', $from)
                ->when($to, fn ($q) => $q->where('trainings.start', '<=', $to))
                ->pluck('trainings.id')
        );

        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return Training::with(['trainingPack', 'room', 'trainer'])
            ->whereIn('id', $sessionIds->unique())
            ->orderBy('start')
            ->get()
            ->map(fn (Training $s): array => $this->trainingRow($s, $packStatusMap));
    }

    /**
     * @param  string[]  $categories
     */
    private function wants(string $category, array $categories): bool
    {
        return $categories === [] || in_array($category, $categories);
    }
}
