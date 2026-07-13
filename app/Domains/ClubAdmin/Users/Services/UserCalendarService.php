<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\Training;
use Carbon\Carbon;
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
     * @return Collection<int, array<string, mixed>> flat list sorted by start datetime
     */
    public function eventsFor(User $user, bool $showAllEvents = false, array $categories = []): Collection
    {
        $events = collect();

        if ($this->wants('tournament', $categories)) {
            $events = $events->merge($this->tournaments($user, $showAllEvents));
        }

        if ($this->wants('training', $categories)) {
            $events = $events->merge($this->trainingSessions($user, $showAllEvents));
        }

        if ($this->wants('meeting', $categories)) {
            $events = $events->merge($this->meetings($user, $showAllEvents));
        }

        if ($this->wants('interclub', $categories)) {
            $events = $events->merge($this->interclubs($user, $showAllEvents));
        }

        return $events->sortBy('startDateTime')->values();
    }

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
    private function interclubs(User $user, bool $showAllEvents): Collection
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
            ->where('season_id', $season->id)
            ->where(fn ($q) => $q->whereIn('visited_team_id', $filterTeamIds)->orWhereIn('visiting_team_id', $filterTeamIds))
            ->where('start_date_time', '>=', Carbon::now())
            ->orderBy('start_date_time')
            ->get()
            ->map(fn ($ic) => $this->formatInterclub($ic, $ourTeamIds->toArray(), $userTeamIds));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function meetings(User $user, bool $showAllEvents): Collection
    {
        $meetingsQuery = Meeting::whereIn('status', [MeetingStatusEnum::CONFIRMED->value])
            ->where('scheduled_at', '>=', now());

        if (! $showAllEvents) {
            $meetingsQuery->whereHas('users', fn ($q) => $q->where('meeting_user.user_id', $user->id));
        }

        return $meetingsQuery
            ->with(['users' => fn ($q) => $q->where('users.id', $user->id)])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn ($m) => [
                'startDateTime' => $m->scheduled_at->format('Y-m-d H:i:s'),
                'title' => $m->title,
                'type' => 'meeting',
                'meetingId' => $m->id,
                'format' => $m->format->value,
                'location' => $m->location,
                'meetingLink' => $m->meeting_link,
                'registrationStatus' => $m->users->first()?->registration?->status?->value,
                'monthKey' => $m->scheduled_at->translatedFormat('F Y'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tournaments(User $user, bool $showAllEvents): Collection
    {
        $tournamentsQuery = Tournament::where('status', TournamentStatusEnum::PUBLISHED)
            ->where('start_date', '>=', now());

        if (! $showAllEvents) {
            $tournamentsQuery->whereHas('users', fn ($q) => $q
                ->where('tournament_user.user_id', $user->id)
                ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
            );
        }

        return $tournamentsQuery
            ->with(['users' => fn ($q) => $q->where('tournament_user.user_id', $user->id)])
            ->orderBy('start_date')
            ->get()
            ->map(fn ($t) => [
                'startDateTime' => $t->start_date->format('Y-m-d H:i:s'),
                'title' => $t->name,
                'type' => 'tournament',
                'tournamentId' => $t->id,
                'registrationStatus' => $t->users->first()?->pivot->registration_status,
                'waitlistPosition' => $t->users->first()?->pivot->waitlist_position,
                'confirmDeadline' => $t->users->first()?->pivot->confirmation_deadline?->format('Y-m-d H:i:s'),
                'monthKey' => $t->start_date->translatedFormat('F Y'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function trainingSessions(User $user, bool $showAllEvents): Collection
    {
        $season = Season::where('is_active', true)->first();

        if (! $season) {
            return collect();
        }

        if ($showAllEvents) {
            return Training::with(['trainingPack', 'room', 'trainer'])
                ->where('status', 'scheduled')
                ->where('start', '>=', Carbon::now())
                ->orderBy('start')
                ->get()
                ->map(fn ($s) => [
                    'startDateTime' => $s->start->format('Y-m-d H:i:s'),
                    'endTime' => $s->end?->format('H:i'),
                    'title' => $s->trainingPack?->name ?? __('Training'),
                    'type' => 'training',
                    'room' => $s->room?->name,
                    'level' => $s->trainingPack?->level?->value,
                    'coach' => $s->trainer ? trim($s->trainer->first_name . ' ' . $s->trainer->last_name) : null,
                    'registrationStatus' => null,
                    'monthKey' => $s->start->translatedFormat('F Y'),
                ]);
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
                    ->where('start', '>=', Carbon::now())
                    ->pluck('id')
            );
        }

        $sessionIds = $sessionIds->merge(
            Training::where('trainer_id', $user->id)
                ->where('status', 'scheduled')
                ->where('start', '>=', Carbon::now())
                ->pluck('id')
        );

        $sessionIds = $sessionIds->merge(
            $user->trainings()
                ->where('trainings.status', 'scheduled')
                ->where('trainings.start', '>=', Carbon::now())
                ->pluck('trainings.id')
        );

        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return Training::with(['trainingPack', 'room', 'trainer'])
            ->whereIn('id', $sessionIds->unique())
            ->orderBy('start')
            ->get()
            ->map(fn ($s) => [
                'startDateTime' => $s->start->format('Y-m-d H:i:s'),
                'endTime' => $s->end?->format('H:i'),
                'title' => $s->trainingPack?->name ?? __('Training'),
                'type' => 'training',
                'room' => $s->room?->name,
                'level' => $s->trainingPack?->level?->value,
                'coach' => $s->trainer ? trim($s->trainer->first_name . ' ' . $s->trainer->last_name) : null,
                'registrationStatus' => null,
                'packId' => $s->training_pack_id,
                'packStatus' => $packStatusMap[$s->training_pack_id]['status'] ?? 'enrolled',
                'confirmDeadline' => $packStatusMap[$s->training_pack_id]['deadline'] ?? null,
                'packWaitlistPosition' => $packStatusMap[$s->training_pack_id]['waitlist_position'] ?? null,
                'monthKey' => $s->start->translatedFormat('F Y'),
            ]);
    }

    /**
     * @param  string[]  $categories
     */
    private function wants(string $category, array $categories): bool
    {
        return empty($categories) || in_array($category, $categories);
    }
}
