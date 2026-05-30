<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Domains\Meetings\Models\Meeting;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Domains\Trainings\Models\Training;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasBreadcrumbs;
    
    public User $user;

    /** @var string[] */
    public array $selectedCategories = [];

    public bool $showAllEvents = false;

    #[Computed]
    public function calendarData(): array
    {
        $events = collect();

        // Tournaments
        if (empty($this->selectedCategories) || in_array('tournament', $this->selectedCategories)) {
            $tournamentsQuery = Tournament::where('status', TournamentStatusEnum::PUBLISHED)
                ->where('start_date', '>=', now());

            if (! $this->showAllEvents) {
                $tournamentsQuery->whereHas('users', fn ($q) => $q
                    ->where('tournament_user.user_id', $this->user->id)
                    ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
                );
            }

            $tournaments = $tournamentsQuery
                ->with(['users' => fn ($q) => $q->where('tournament_user.user_id', $this->user->id)])
                ->orderBy('start_date')
                ->get()
                ->map(fn ($t) => [
                    'startDateTime'      => $t->start_date->format('Y-m-d H:i:s'),
                    'title'              => $t->name,
                    'type'               => 'tournament',
                    'tournamentId'       => $t->id,
                    'registrationStatus' => $t->users->first()?->pivot->registration_status,
                    'waitlistPosition'   => $t->users->first()?->pivot->waitlist_position,
                    'confirmDeadline'    => $t->users->first()?->pivot->confirmation_deadline?->format('Y-m-d H:i:s'),
                    'monthKey'           => $t->start_date->translatedFormat('F Y'),
                ]);

            $events = $events->merge($tournaments);
        }

        // Training sessions
        if (empty($this->selectedCategories) || in_array('training', $this->selectedCategories)) {
            $season = Season::where('is_active', true)->first();

            if ($season) {
                if ($this->showAllEvents) {
                    $sessions = Training::with(['trainingPack', 'room', 'trainer'])
                        ->where('status', 'scheduled')
                        ->where('start', '>=', Carbon::now())
                        ->orderBy('start')
                        ->get()
                        ->map(fn ($s) => [
                            'startDateTime'      => $s->start->format('Y-m-d H:i:s'),
                            'endTime'            => $s->end?->format('H:i'),
                            'title'              => $s->trainingPack?->name ?? __('Training'),
                            'type'               => 'training',
                            'room'               => $s->room?->name,
                            'level'              => $s->trainingPack?->level?->value,
                            'coach'              => $s->trainer ? trim($s->trainer->first_name . ' ' . $s->trainer->last_name) : null,
                            'registrationStatus' => null,
                            'monthKey'           => $s->start->translatedFormat('F Y'),
                        ]);

                    $events = $events->merge($sessions);
                } else {
                    // Sessions the user is personally involved in
                    // Build a pack → pivot status map so we can show the right badge
                    $subs = $this->user->subscriptions()
                        ->where('season_id', $season->id)
                        ->whereNotIn('status', ['cancelled'])
                        ->with('trainingPacks')
                        ->get();

                    $packStatusMap = [];
                    foreach ($subs as $sub) {
                        foreach ($sub->trainingPacks as $pack) {
                            $packStatusMap[$pack->id] = [
                                'status'            => $pack->pivot->status,
                                'deadline'          => $pack->pivot->confirmation_deadline,
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
                        Training::where('trainer_id', $this->user->id)
                            ->where('status', 'scheduled')
                            ->where('start', '>=', Carbon::now())
                            ->pluck('id')
                    );

                    $sessionIds = $sessionIds->merge(
                        $this->user->trainings()
                            ->where('trainings.status', 'scheduled')
                            ->where('trainings.start', '>=', Carbon::now())
                            ->pluck('trainings.id')
                    );

                    if ($sessionIds->isNotEmpty()) {
                        $sessions = Training::with(['trainingPack', 'room', 'trainer'])
                            ->whereIn('id', $sessionIds->unique())
                            ->orderBy('start')
                            ->get()
                            ->map(fn ($s) => [
                                'startDateTime'      => $s->start->format('Y-m-d H:i:s'),
                                'endTime'            => $s->end?->format('H:i'),
                                'title'              => $s->trainingPack?->name ?? __('Training'),
                                'type'               => 'training',
                                'room'               => $s->room?->name,
                                'level'              => $s->trainingPack?->level?->value,
                                'coach'              => $s->trainer ? trim($s->trainer->first_name . ' ' . $s->trainer->last_name) : null,
                                'registrationStatus' => null,
                                'packId'               => $s->training_pack_id,
                                'packStatus'           => $packStatusMap[$s->training_pack_id]['status'] ?? 'enrolled',
                                'confirmDeadline'      => $packStatusMap[$s->training_pack_id]['deadline'] ?? null,
                                'packWaitlistPosition' => $packStatusMap[$s->training_pack_id]['waitlist_position'] ?? null,
                                'monthKey'           => $s->start->translatedFormat('F Y'),
                            ]);

                        $events = $events->merge($sessions);
                    }
                }
            }
        }

        // Meetings (committee + GA)
        if (empty($this->selectedCategories) || in_array('meeting', $this->selectedCategories)) {
            $meetingsQuery = Meeting::whereIn('status', [MeetingStatusEnum::CONFIRMED->value])
                ->where('scheduled_at', '>=', now());

            if (! $this->showAllEvents) {
                $meetingsQuery->whereHas('users', fn ($q) => $q->where('meeting_user.user_id', $this->user->id));
            }

            $meetings = $meetingsQuery
                ->with(['users' => fn ($q) => $q->where('users.id', $this->user->id)])
                ->orderBy('scheduled_at')
                ->get()
                ->map(fn ($m) => [
                    'startDateTime'      => $m->scheduled_at->format('Y-m-d H:i:s'),
                    'title'              => $m->title,
                    'type'               => 'meeting',
                    'meetingId'          => $m->id,
                    'format'             => $m->format->value,
                    'location'           => $m->location,
                    'meetingLink'        => $m->meeting_link,
                    'registrationStatus' => $m->users->first()?->registration?->status?->value,
                    'monthKey'           => $m->scheduled_at->translatedFormat('F Y'),
                ]);

            $events = $events->merge($meetings);
        }

        // Interclub matches
        if (empty($this->selectedCategories) || in_array('interclub', $this->selectedCategories)) {
            $season = Season::where('is_active', true)->first();

            if ($season) {
                $ourTeamIds = Team::inClub()->where('season_id', $season->id)->pluck('id');

                if ($ourTeamIds->isNotEmpty()) {
                    $userTeamIds = $this->user->teams()->pluck('teams.id')->toArray();

                    // In "My events" mode, only show matches for the user's own teams
                    $filterTeamIds = $this->showAllEvents
                        ? $ourTeamIds->toArray()
                        : $userTeamIds;

                    if (! empty($filterTeamIds)) {
                        $interclubs = Interclub::with([
                            'visitedTeam.club',
                            'visitingTeam.club',
                            'league',
                            'users' => fn ($q) => $q->where('users.id', $this->user->id),
                        ])
                            ->where('season_id', $season->id)
                            ->where(fn ($q) => $q->whereIn('visited_team_id', $filterTeamIds)->orWhereIn('visiting_team_id', $filterTeamIds))
                            ->where('start_date_time', '>=', Carbon::now())
                            ->orderBy('start_date_time')
                            ->get()
                            ->map(fn ($ic) => $this->formatInterclub($ic, $ourTeamIds->toArray(), $userTeamIds));

                        $events = $events->merge($interclubs);
                    }
                }
            }
        }

        return $events
            ->sortBy('startDateTime')
            ->groupBy('monthKey')
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Calendar'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'calendar'   => $this->calendarData,
            'categories' => [
                ['id' => 'tournament', 'name' => __('Tournament')],
                ['id' => 'training',   'name' => __('Training')],
                ['id' => 'interclub',  'name' => __('Interclub')],
                ['id' => 'meeting',    'name' => __('Meeting')],
            ],
            'selectedCategories' => $this->selectedCategories,
        ];
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
            'startDateTime'   => $ic->start_date_time->format('Y-m-d H:i:s'),
            'title'           => ($ourTeam?->name ?? '') . ' vs ' . $opponent,
            'type'            => 'interclub',
            'isHome'          => $isHome,
            'opponent'        => $opponent,
            'teamName'        => $ourTeam?->name ?? '—',
            'division'        => $ic->league?->division ?? '',
            'address'         => $ic->address ?? '—',
            'isUserInTeam'    => in_array($ourTeam?->id, $userTeamIds),
            'availability'    => $availability,
            'isSelected'      => (bool) $pivot?->is_selected,
            'registrationStatus' => null,
            'monthKey'        => $ic->start_date_time->translatedFormat('F Y'),
        ];
    }
};
