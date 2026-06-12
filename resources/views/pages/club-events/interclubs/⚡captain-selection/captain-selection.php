<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\CaptainSelection;

use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubAvailabilityService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs, HasFilterDrawer;

    public string $captainMeetupInfo = '';

    public bool $drawerSelection = false;

    public bool $modalMessage = false;

    public ?int $selectedSeasonId = null;

    public ?int $selectedInterclubId = null;

    public ?int $selectedTeamId = null;

    public string $search = '';

    /** @var array<int, int> */
    public array $selectedPlayerIds = [];

    #[Url]
    public ?int $zoomedTeamId = null;

    #[Locked]
    public ?int $currentUserId = null;

    public function boot(): void
    {
        $this->currentUserId = Auth::id();
    }

    public function mount(): void
    {
        $this->selectedSeasonId = Season::current()?->id;

        $user = Auth::user();
        if (! $user->is_admin && ! $user->is_committee_member && ! $user->is_selector) {
            $isCaptain = Team::where('captain_id', $user->id)->exists();
            abort_unless($isCaptain, 403);
        }

        $this->selectedTeamId = $this->loadAccessibleTeams($user, Season::current())->first()?->id;
    }

    public function updatedSelectedSeasonId(): void
    {
        $user = Auth::user();
        $season = $this->selectedSeasonId ? Season::find($this->selectedSeasonId) : Season::current();
        $this->selectedTeamId = $this->loadAccessibleTeams($user, $season)->first()?->id;
        $this->selectedInterclubId = null;
        $this->selectedPlayerIds = [];
        $this->drawerSelection = false;
    }

    public function updatedSelectedTeamId(): void
    {
        $this->selectedInterclubId = null;
        $this->selectedPlayerIds = [];
        $this->drawerSelection = false;
    }

    public function sendLineupToTeam(InterclubAvailabilityService $service): void
    {
        $interclub = $this->selectedInterclub();

        if (! $interclub) {
            return;
        }

        $service->confirmSelection($interclub, $this->captainMeetupInfo);

        $this->modalMessage = false;
        $this->captainMeetupInfo = '';

        $this->success(
            __('Lineup sent to the whole team!'),
            __('All team members have been notified.'),
            icon: 'o-paper-airplane'
        );
    }

    public function skipSending(): void
    {
        $this->modalMessage = false;
        $this->captainMeetupInfo = '';
        $this->success(__('Selection saved.'), position: 'toast-bottom toast-end');
    }

    public function openSelection(int $interclubId): void
    {
        $interclub = Interclub::findOrFail($interclubId);

        if ($interclub->start_date_time < now()) {
            return;
        }

        $this->selectedInterclubId = $interclubId;

        $this->selectedPlayerIds = $interclub->users()
            ->wherePivot('is_selected', true)
            ->pluck('users.id')
            ->toArray();

        $this->drawerSelection = true;
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Captain Selection"));
    }

        public function render(): View
    {
        return $this->view();
    }

    public function requestAvailability(int $interclubId, InterclubAvailabilityService $service): void
    {
        $interclub = Interclub::findOrFail($interclubId);
        $service->requestAvailability($interclub);

        $this->success(
            __('Availability request sent!'),
            __('Team members who have not responded will receive an email.'),
            position: 'toast-bottom toast-end'
        );
    }

    public function saveSelection(): void
    {
        $interclub = $this->selectedInterclub();

        if (! $interclub) {
            return;
        }

        abort_if($interclub->start_date_time < now(), 403);

        // Safety re-check: block if any selected player double-booked this week
        foreach ($this->selectedPlayerIds as $selectedId) {
            if ($this->isPlayerDoubleBooked($selectedId, $interclub)) {
                $this->error(
                    __('Selection blocked'),
                    __('One or more players are already selected in another team for week :n.', ['n' => $interclub->week_number]),
                    position: 'toast-bottom toast-end'
                );

                return;
            }
        }

        $existingIds = $interclub->users()->pluck('users.id')->toArray();
        $maxPlayers = $interclub->total_players;

        if (count($this->selectedPlayerIds) > $maxPlayers) {
            $this->warning(
                __('Too many players'),
                __('Maximum :n players allowed.', ['n' => $maxPlayers]),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        foreach ($this->selectedPlayerIds as $userId) {
            if (in_array($userId, $existingIds)) {
                $interclub->users()->updateExistingPivot($userId, ['is_selected' => true]);
            } else {
                $interclub->users()->attach($userId, ['is_selected' => true]);
            }
        }

        $toDeselect = array_diff($existingIds, $this->selectedPlayerIds);
        foreach ($toDeselect as $userId) {
            $interclub->users()->updateExistingPivot($userId, ['is_selected' => false]);
        }

        $this->drawerSelection = false;
        $this->modalMessage = true;
    }

    public function togglePlayer(int $userId): void
    {
        $interclub = $this->selectedInterclub();
        $maxPlayers = $interclub?->total_players ?? 4;

        if (in_array($userId, $this->selectedPlayerIds)) {
            $this->selectedPlayerIds = array_values(array_diff($this->selectedPlayerIds, [$userId]));

            return;
        }

        if ($interclub && $this->isPlayerDoubleBooked($userId, $interclub)) {
            $this->warning(
                __('Player already aligned'),
                __('This player is already selected in another team for week :n.', ['n' => $interclub->week_number]),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        if (count($this->selectedPlayerIds) >= $maxPlayers) {
            $this->warning(
                __('Team full'),
                __('Maximum :n players.', ['n' => $maxPlayers]),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        $this->selectedPlayerIds[] = $userId;
    }

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $user = Auth::user();
        $season = $this->selectedSeasonId ? Season::find($this->selectedSeasonId) : Season::current();
        $teams = $this->loadAccessibleTeams($user, $season);

        if ($teams->count() <= 1 || ! $this->selectedTeamId) {
            return [];
        }

        if ($this->selectedTeamId === $teams->first()?->id) {
            return [];
        }

        $team = $teams->firstWhere('id', $this->selectedTeamId);

        if (! $team) {
            return [];
        }

        $label = $team->name . ($team->league?->category ? ' · ' . $this->categoryLabel($team->league->category) : '');

        return [['key' => 'selectedTeamId', 'label' => $label]];
    }

    public function clearFilters(): void
    {
        $user = Auth::user();
        $season = $this->selectedSeasonId ? Season::find($this->selectedSeasonId) : Season::current();
        $this->selectedTeamId = $this->loadAccessibleTeams($user, $season)->first()?->id;
    }

    public function removeFilter(string $_key): void
    {
        $this->clearFilters();
    }

    private function categoryLabel(string $category): string
    {
        return match($category) {
            'MEN' => __('Men'),
            'WOMEN' => __('Women'),
            'VETERANS' => __('Veterans'),
            default => $category,
        };
    }

    public function with(): array
    {
        $user = Auth::user();
        $isAdminOrCommittee = $user->is_admin || $user->is_committee_member;
        $canSearchSubstitute = $user->is_admin || $user->is_committee_member || $user->is_selector;

        $seasons = Season::orderBy('start_at')->get();
        $season = $this->selectedSeasonId
            ? $seasons->firstWhere('id', $this->selectedSeasonId)
            : Season::current();

        $teams = $this->loadAccessibleTeams($user, $season);

        $teamsData = $teams->map(fn (Team $team) => $this->buildTeamData($team, $season));

        if ($this->selectedTeamId && ! $teamsData->firstWhere('id', $this->selectedTeamId)) {
            $this->selectedTeamId = $teamsData->first()['id'] ?? null;
        }

        $alertMatches = $teamsData
            ->flatMap(fn ($t) => collect($t['matches'])->map(fn ($m) => array_merge($m, ['team_name' => $t['name'], 'team_id' => $t['id']])))
            ->filter(fn ($m) => $m['status'] === 'urgent')
            ->values();

        // Drawer data: roster for the selected match
        $drawerInterclub = null;
        $roster = collect();
        $maxPlayers = 4;
        $blockedPlayerIds = [];

        if ($this->selectedInterclubId && $this->drawerSelection) {
            $drawerInterclub = Interclub::find($this->selectedInterclubId);

            if ($drawerInterclub) {
                $selectedTeam = $teams->firstWhere('id', $this->selectedTeamId);
                $maxPlayers = $drawerInterclub->total_players;

                $pivotMap = $drawerInterclub->users()
                    ->get()
                    ->keyBy('id')
                    ->map(fn ($u) => $u->registration);

                // Players selected in another team same week -> blocked
                $blockedPlayerData = [];
                $sameWeekMatches = Interclub::where('season_id', $drawerInterclub->season_id)
                    ->where('week_number', $drawerInterclub->week_number)
                    ->where('id', '!=', $drawerInterclub->id)
                    ->with(['visitedTeam.club', 'visitingTeam.club'])
                    ->get();

                foreach ($sameWeekMatches as $swMatch) {
                    $swTeam = $swMatch->visitedTeam?->club?->is_own_club
                        ? $swMatch->visitedTeam
                        : $swMatch->visitingTeam;

                    foreach ($swMatch->users()->wherePivot('is_selected', true)->pluck('users.id') as $uid) {
                        $blockedPlayerData[$uid] = $swTeam?->name ?? '?';
                    }
                }

                $blockedPlayerIds = array_keys($blockedPlayerData);

                // Roster from team members
                $roster = ($selectedTeam?->users ?? collect())->map(
                    fn (User $player) => $this->buildPlayerData($player, $pivotMap, $selectedTeam, $season, $blockedPlayerData)
                )->sortBy([
                    ['rank_sort', 'asc'],
                    ['last_name', 'asc'],
                    ['first_name', 'asc'],
                ])->values();

                // Add substitutes: selected players not on the team
                $teamUserIds = $selectedTeam?->users->pluck('id')->toArray() ?? [];
                $substituteIds = array_diff($this->selectedPlayerIds, $teamUserIds);

                if (! empty($substituteIds)) {
                    $substitutes = User::whereIn('id', $substituteIds)->get()->map(
                        fn (User $player) => $this->buildPlayerData($player, $pivotMap, $selectedTeam, $season, $blockedPlayerData)
                    )->values();
                    $roster = $roster->concat($substitutes)->values();
                }
            }
        }

        $searchResults = collect();
        if ($canSearchSubstitute && strlen($this->search) >= 2) {
            $selectedTeam = $teams->firstWhere('id', $this->selectedTeamId);
            $teamCategory = $selectedTeam?->league?->category;

            $searchResults = User::where('is_competitor', true)
                ->where(fn ($q) => $q
                    ->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('last_name', 'like', '%' . $this->search . '%'))
                ->whereNotIn('id', array_merge($this->selectedPlayerIds, $blockedPlayerIds))
                ->when($teamCategory === 'MEN', fn ($q) => $q->where('gender', Gender::MEN))
                ->when($teamCategory === 'WOMEN', fn ($q) => $q->where('gender', Gender::WOMEN))
                ->when($teamCategory === 'VETERANS' && $season, fn ($q) => $q->whereDate('birthdate', '<=', $season->end_at->copy()->subYears(40)))
                ->limit(8)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->last_name . ' ' . $u->first_name,
                    'rank' => $u->ranking ?? '—',
                ]);
        }

        $allTeamsForSummary = $isAdminOrCommittee
            ? Team::with(['league'])->inClub()->when($season, fn ($q) => $q->where('season_id', $season->id))->get()
            : collect();

        $matchDayMap = $season ? Interclub::matchDayMap($season->id, $teams->pluck('id')->toArray()) : [];

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'seasons_list' => $seasons->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'teams_list' => $teams->map(fn ($t) => ['id' => $t->id, 'name' => ($t->club?->name ?? '?') . ' ' . $t->name]),
            'teams_for_filter' => $teams->count() > 1
                ? $teams
                    ->sortBy(fn (Team $t) => sprintf('%d_%s', ['MEN' => 0, 'WOMEN' => 1, 'VETERANS' => 2][$t->league?->category] ?? 99, $t->name))
                    ->map(fn (Team $t) => [
                        'id' => $t->id,
                        'name' => $t->name . ($t->league?->category ? ' · ' . $this->categoryLabel($t->league->category) : ''),
                    ])->values()->all()
                : [],
            'teamsData' => $teamsData,
            'alertMatches' => $alertMatches,
            'roster' => $roster,
            'maxPlayers' => $maxPlayers,
            'searchResults' => $searchResults,
            'drawerInterclub' => $drawerInterclub,
            'isAdminOrCommittee' => $isAdminOrCommittee,
            'canSearchSubstitute' => $canSearchSubstitute,
            'weekSummary' => $isAdminOrCommittee ? $this->buildWeekSummary($allTeamsForSummary, $this->zoomedTeamId) : null,
            'matchDayMap' => $matchDayMap,
            'zoomedTeamId' => $this->zoomedTeamId,
            'filterChips' => $this->getFilterChips(),
        ];
    }

    /** @return array<string, mixed> */
    private function buildTeamData(Team $team, ?Season $season): array
    {
        $teamUserIds = $team->users->pluck('id');
        $teamMemberCount = $teamUserIds->count();

        $interclubs = Interclub::with([
            'visitedTeam.club',
            'visitingTeam.club',
            'users',
        ])
            ->where(fn ($q) => $q
                ->where('visited_team_id', $team->id)
                ->orWhere('visiting_team_id', $team->id))
            ->orderBy('start_date_time')
            ->get();

        $matches = $interclubs->map(function (Interclub $ic) use ($team, $teamMemberCount) {
            $ourTeam = $ic->visitedTeam?->club?->is_own_club
                ? $ic->visitedTeam
                : $ic->visitingTeam;

            $isHome = $ic->visitedTeam?->id === $ourTeam?->id;
            $opponentTeam = $isHome ? $ic->visitingTeam : $ic->visitedTeam;
            $opponent = trim(($opponentTeam?->club?->name ?? '') . ' ' . ($opponentTeam?->name ?? '')) ?: '—';

            $icUsers = $ic->users;

            $availableCount = $icUsers->filter(fn ($u) => $u->registration?->availability === 'available')->count();
            $maybeCount = $icUsers->filter(fn ($u) => $u->registration?->availability === 'maybe')->count();
            $unavailCount = $icUsers->filter(fn ($u) => $u->registration?->availability === 'unavailable')->count();
            $respondedCount = $availableCount + $maybeCount + $unavailCount;
            $pendingCount = max(0, $teamMemberCount - $respondedCount);

            $selectedCount = $icUsers->filter(fn ($u) => $u->registration?->is_selected)->count();
            $confirmedAtCount = $icUsers->filter(fn ($u) => $u->registration?->is_selected && $u->registration?->selection_confirmed_at)->count();

            $isPast = $ic->start_date_time < now();
            $daysUntil = (int) now()->diffInDays($ic->start_date_time, false);

            $status = match (true) {
                $isPast => 'past',
                $confirmedAtCount > 0 => 'confirmed',
                $selectedCount >= $ic->total_players => 'actionable',
                $availableCount >= $ic->total_players => 'actionable',
                $daysUntil <= 14 => 'urgent',
                default => 'future',
            };

            $selectedPlayerNames = $isPast
                ? $icUsers->filter(fn ($u) => $u->registration?->is_selected)
                    ->map(fn ($u) => $u->last_name . ' ' . $u->first_name)
                    ->values()
                    ->toArray()
                : [];

            return [
                'id' => $ic->id,
                'wk' => $ic->week_number,
                'date' => $ic->start_date_time->format('d/m/Y'),
                'time' => $ic->start_date_time->format('H:i'),
                'opponent' => $opponent,
                'is_home' => $isHome,
                'status' => $status,
                'is_past' => $isPast,
                'days_until' => $daysUntil,
                'available_count' => $availableCount,
                'maybe_count' => $maybeCount,
                'unavail_count' => $unavailCount,
                'pending_count' => $pendingCount,
                'selected_count' => $selectedCount,
                'max_players' => $ic->total_players,
                'selected_player_names' => $selectedPlayerNames,
            ];
        });

        return [
            'id' => $team->id,
            'name' => $team->name,
            'division' => $team->league?->division ?? '—',
            'captain_name' => trim(($team->captain?->last_name ?? '') . ' ' . ($team->captain?->first_name ?? '')),
            'matches' => $matches->values()->toArray(),
            'has_alert' => $matches->where('status', 'urgent')->isNotEmpty(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $pivotMap
     * @param  array<int, string>  $blockedPlayerData  user_id => team_name already selected this week
     * @return array<string, mixed>
     */
    private function buildPlayerData(User $player, Collection $pivotMap, ?Team $team, ?Season $season, array $blockedPlayerData = []): array
    {
        $pivot = $pivotMap->get($player->id);
        $avail = $pivot?->availability
            ? InterclubAvailability::from($pivot->availability)
            : null;

        $matchesPlayed = $season && $team
            ? $this->matchesPlayedCount($player->id, $team->id, $season)
            : 0;

        $matchesSelected = $season && $team
            ? $this->matchesSelectedCount($player->id, $team->id, $season)
            : 0;

        return [
            'id' => $player->id,
            'name' => $player->last_name . ' ' . $player->first_name,
            'last_name' => $player->last_name ?? '',
            'first_name' => $player->first_name ?? '',
            'rank' => $player->ranking ?? '—',
            'rank_sort' => $player->ranking ?? 'ZZZ',
            'availability' => $avail,
            'availability_note' => $pivot?->availability_note,
            'matches_played' => $matchesPlayed,
            'matches_selected' => $matchesSelected,
            'is_blocked' => isset($blockedPlayerData[$player->id]),
            'blocked_team' => $blockedPlayerData[$player->id] ?? null,
        ];
    }

    private function matchesPlayedCount(int $userId, int $teamId, Season $season): int
    {
        return Interclub::where('season_id', $season->id)
            ->where('start_date_time', '<', now())
            ->where(fn ($q) => $q
                ->where('visited_team_id', $teamId)
                ->orWhere('visiting_team_id', $teamId))
            ->whereHas('users', fn ($q) => $q
                ->where('users.id', $userId)
                ->where('interclub_user.has_played', 1))
            ->count();
    }

    private function matchesSelectedCount(int $userId, int $teamId, Season $season): int
    {
        return Interclub::where('season_id', $season->id)
            ->where(fn ($q) => $q
                ->where('visited_team_id', $teamId)
                ->orWhere('visiting_team_id', $teamId))
            ->whereHas('users', fn ($q) => $q
                ->where('users.id', $userId)
                ->where('interclub_user.is_selected', 1))
            ->count();
    }

    private function loadAccessibleTeams(User $user, ?Season $season): \Illuminate\Database\Eloquent\Collection
    {
        $query = Team::with(['league', 'club', 'captain', 'users'])->inClub();

        if ($season) {
            $query->where('season_id', $season->id);
        }

        if (! $user->is_admin && ! $user->is_committee_member && ! $user->is_selector) {
            $query->where('captain_id', $user->id);
        }

        return $query->orderBy('name')->get();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
    private function buildWeekSummary(\Illuminate\Database\Eloquent\Collection $teams, ?int $zoomedTeamId = null): array
    {
        if ($zoomedTeamId) {
            $teams = $teams->filter(fn (Team $t) => $t->id === $zoomedTeamId)->values();
        }

        $weekNumbers = $this->weekNumbers($teams);

        $weeks = $weekNumbers->map(fn (int $wk) => [
            'wk' => $wk,
            'status' => $this->weekStatus($wk, $teams),
        ])->values()->all();

        $total = $weekNumbers->count();
        $ok = collect($weeks)->where('status', 'confirmed')->count();

        return [
            'weeks' => $weeks,
            'preparation_score' => $total > 0 ? (int) round($ok / $total * 100) : 0,
            'total' => $total,
            'ok' => $ok,
            'matrix' => $this->teamWeekMatrix($teams, $weekNumbers),
            'teams' => $teams->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name])->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Team>  $teams
     * @return array<int, array<int, string|null>>
     */
    private function teamWeekMatrix(\Illuminate\Database\Eloquent\Collection $teams, Collection $weekNumbers): array
    {
        $matrix = [];
        foreach ($teams as $team) {
            $matrix[$team->id] = [];
            foreach ($weekNumbers as $wk) {
                $interclub = Interclub::where('week_number', $wk)
                    ->where(fn ($q) => $q
                        ->where('visited_team_id', $team->id)
                        ->orWhere('visiting_team_id', $team->id))
                    ->first();
                $matrix[$team->id][$wk] = $interclub ? $this->weekStatus($wk, Team::newModelInstance()->newCollection([$team])) : null;
            }
        }

        return $matrix;
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
    private function weekNumbers(\Illuminate\Database\Eloquent\Collection $teams): Collection
    {
        $teamIds = $teams->pluck('id');

        return Interclub::whereIn('visited_team_id', $teamIds)
            ->orWhereIn('visiting_team_id', $teamIds)
            ->whereNotNull('week_number')
            ->orderBy('start_date_time')
            ->pluck('week_number')
            ->unique()
            ->values();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
    private function weekStatus(int $weekNumber, \Illuminate\Database\Eloquent\Collection $teams): string
    {
        $worstStatus = 'confirmed';

        foreach ($teams as $team) {
            $interclub = Interclub::with('users')->where('week_number', $weekNumber)
                ->where(fn ($q) => $q
                    ->where('visited_team_id', $team->id)
                    ->orWhere('visiting_team_id', $team->id))
                ->first();

            if (! $interclub) {
                continue;
            }

            if ($interclub->start_date_time < now()) {
                continue;
            }

            $users = $interclub->users;
            $maxPlayers = $interclub->total_players;
            $daysUntil = (int) now()->diffInDays($interclub->start_date_time, false);

            $confirmedAtCount = $users->filter(fn ($u) => $u->registration?->is_selected && $u->registration?->selection_confirmed_at)->count();
            $selectedCount = $users->filter(fn ($u) => $u->registration?->is_selected)->count();
            $availableCount = $users->filter(fn ($u) => $u->registration?->availability === 'available')->count();

            $matchStatus = match (true) {
                $confirmedAtCount > 0 => 'confirmed',
                $selectedCount >= $maxPlayers => 'actionable',
                $availableCount >= $maxPlayers => 'actionable',
                $daysUntil <= 14 => 'urgent',
                default => 'future',
            };

            $worstStatus = $this->worstOf($worstStatus, $matchStatus);
        }

        return $worstStatus;
    }

    private function worstOf(string $a, string $b): string
    {
        $rank = ['confirmed' => 0, 'actionable' => 1, 'future' => 2, 'urgent' => 3];

        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    private function selectedInterclub(): ?Interclub
    {
        if (! $this->selectedInterclubId) {
            return null;
        }

        return Interclub::find($this->selectedInterclubId);
    }

    private function isPlayerDoubleBooked(int $userId, Interclub $interclub): bool
    {
        return Interclub::where('season_id', $interclub->season_id)
            ->where('week_number', $interclub->week_number)
            ->where('id', '!=', $interclub->id)
            ->whereHas('users', fn ($q) => $q
                ->where('users.id', $userId)
                ->where('interclub_user.is_selected', 1))
            ->exists();
    }
};
