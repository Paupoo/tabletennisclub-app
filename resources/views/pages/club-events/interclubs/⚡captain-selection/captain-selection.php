<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\CaptainSelection;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubAvailabilityService;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast;

    public string $captainMeetupInfo = '';

    #[Locked]
    public ?int $currentUserId = null;

    public bool $drawerSelection = false;

    public bool $isUpdateMode = false;

    public bool $modalMessage = false;

    /** @var array<int, int> */
    public array $pendingAddedIds = [];

    /** @var array<int, int> */
    public array $pendingRemovedIds = [];

    public string $search = '';

    #[Locked]
    public ?int $selectedInterclubId = null;

    /** @var array<int, int> */
    public array $selectedPlayerIds = [];

    public ?int $selectedSeasonId = null;

    public ?int $selectedTeamId = null;

    public function boot(): void
    {
        $this->currentUserId = Auth::id();
    }

    public function clearFilters(): void
    {
        $this->selectedSeasonId = Season::current()?->id;

        $user = Auth::user();
        $this->selectedTeamId = $this->loadAccessibleTeams($user, Season::current())->first()?->id;
    }

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->selectedSeasonId !== Season::current()?->id) {
            $seasonName = Season::find($this->selectedSeasonId)?->name ?? __('All seasons');
            $chips[] = ['key' => 'selectedSeasonId', 'label' => __('Season') . ': ' . $seasonName];
        }

        $user = Auth::user();
        $season = $this->selectedSeasonId ? Season::find($this->selectedSeasonId) : Season::current();
        $teams = $this->loadAccessibleTeams($user, $season);

        if ($teams->count() > 1 && $this->selectedTeamId && $this->selectedTeamId !== $teams->first()?->id) {
            $team = $teams->firstWhere('id', $this->selectedTeamId);

            if ($team) {
                $label = $team->name . ($team->league?->category ? ' · ' . $this->categoryLabel($team->league->category) : '');
                $chips[] = ['key' => 'selectedTeamId', 'label' => $label];
            }
        }

        return $chips;
    }

    public function mount(): void
    {
        $this->selectedSeasonId = Season::current()?->id;

        $user = Auth::user();

        // Selections delegate, or captain of at least one team.
        Gate::authorize('access-selections');

        $this->selectedTeamId = $this->loadAccessibleTeams($user, Season::current())->first()?->id;
    }

    public function openSelection(int $interclubId): void
    {
        $interclub = Interclub::findOrFail($interclubId);
        $this->authorizeInterclub($interclub);

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

    public function removeFilter(string $key): void
    {
        if ($key === 'selectedSeasonId') {
            $this->selectedSeasonId = Season::current()?->id;
        }

        $user = Auth::user();
        $season = $this->selectedSeasonId ? Season::find($this->selectedSeasonId) : Season::current();
        $this->selectedTeamId = $this->loadAccessibleTeams($user, $season)->first()?->id;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function requestAvailability(int $interclubId, InterclubAvailabilityService $service): void
    {
        $interclub = Interclub::findOrFail($interclubId);
        $this->authorizeInterclub($interclub);

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

        $previouslyConfirmedIds = $interclub->users()
            ->wherePivot('is_selected', true)
            ->wherePivotNotNull('selection_confirmed_at')
            ->pluck('users.id')
            ->toArray();

        DB::transaction(function () use ($interclub, $existingIds): void {
            foreach ($this->selectedPlayerIds as $userId) {
                if (in_array($userId, $existingIds, true)) {
                    $interclub->users()->updateExistingPivot($userId, ['is_selected' => true]);
                } else {
                    $interclub->users()->attach($userId, ['is_selected' => true]);
                }
            }

            $toDeselect = array_diff($existingIds, $this->selectedPlayerIds);
            foreach ($toDeselect as $userId) {
                $interclub->users()->updateExistingPivot($userId, ['is_selected' => false]);
            }
        });

        $this->drawerSelection = false;

        if ($previouslyConfirmedIds === []) {
            if ($interclub->isSelectionComplete()) {
                $this->isUpdateMode = false;
                $this->modalMessage = true;
            } else {
                $this->success(__('Selection saved.'), position: 'toast-bottom toast-end');
            }

            return;
        }

        $added = array_values(array_diff($this->selectedPlayerIds, $previouslyConfirmedIds));
        $removed = array_values(array_diff($previouslyConfirmedIds, $this->selectedPlayerIds));

        if ($added === [] && $removed === []) {
            $this->success(__('Selection saved.'), position: 'toast-bottom toast-end');

            return;
        }

        $this->pendingAddedIds = $added;
        $this->pendingRemovedIds = $removed;
        $this->isUpdateMode = true;
        $this->modalMessage = true;
    }

    public function sendLineupToTeam(InterclubAvailabilityService $service): void
    {
        $interclub = $this->selectedInterclub();

        if (! $interclub) {
            return;
        }

        if ($this->isUpdateMode) {
            $service->notifySelectionChange($interclub, $this->pendingAddedIds, $this->pendingRemovedIds, $this->captainMeetupInfo);
        } else {
            $service->confirmSelection($interclub, $this->captainMeetupInfo);
        }

        $this->resetSendModal();

        $this->success(
            __('Lineup sent to the whole team!'),
            __('All team members have been notified.'),
            icon: 'o-paper-airplane'
        );
    }

    public function skipSending(): void
    {
        $this->resetSendModal();
        $this->success(__('Selection saved.'), position: 'toast-bottom toast-end');
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

    public function with(): array
    {
        $user = Auth::user();
        $isAdminOrCommittee = $user->can(Permission::InterclubsManage->value);
        $canSearchSubstitute = $user->can(Permission::SelectionsManage->value);

        $seasons = Season::orderBy('start_at')->get();
        $season = $this->selectedSeasonId
            ? $seasons->firstWhere('id', $this->selectedSeasonId)
            : Season::current();

        $teams = $this->loadAccessibleTeams($user, $season);

        $allTeamsForSummary = $isAdminOrCommittee
            ? Team::with(['league'])->inClub()->when($season, fn ($q) => $q->where('season_id', $season->id))->get()
            : Team::newModelInstance()->newCollection();

        // Single pass: every fixture this render needs, loaded once with the
        // pivot rows the status rule depends on. The team cards and the
        // preparation matrix both read from here — they used to each query per
        // team, and the matrix additionally queried per cell, which is what put
        // a nine-team season past a thousand queries per render.
        $fixtures = $this->loadFixtures(
            $teams->pluck('id')->merge($allTeamsForSummary->pluck('id'))->unique()->values()->all()
        );

        $teamsData = $teams->map(fn (Team $team) => $this->buildTeamData($team, $fixtures));

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
            $drawerInterclub = $fixtures->firstWhere('id', $this->selectedInterclubId)
                ?? Interclub::find($this->selectedInterclubId);

            if ($drawerInterclub) {
                $selectedTeam = $teams->firstWhere('id', $this->selectedTeamId);
                $maxPlayers = $drawerInterclub->total_players;

                $pivotMap = $drawerInterclub->users
                    ->keyBy('id')
                    ->map(fn ($u) => $u->registration);

                // Players selected in another team same week -> blocked. This
                // deliberately keeps its own query: a plain captain only has
                // their own teams in $fixtures, and the whole point is to catch
                // a player lined up by someone else.
                $blockedPlayerData = [];
                $sameWeekMatches = Interclub::where('season_id', $drawerInterclub->season_id)
                    ->where('week_number', $drawerInterclub->week_number)
                    ->where('id', '!=', $drawerInterclub->id)
                    ->with([
                        'visitedTeam.club',
                        'visitingTeam.club',
                        'users' => fn ($q) => $q->wherePivot('is_selected', true),
                    ])
                    ->get();

                foreach ($sameWeekMatches as $swMatch) {
                    $swTeam = $swMatch->visitedTeam?->club?->is_own_club
                        ? $swMatch->visitedTeam
                        : $swMatch->visitingTeam;

                    foreach ($swMatch->users as $swUser) {
                        $blockedPlayerData[$swUser->id] = $swTeam?->name ?? '?';
                    }
                }

                $blockedPlayerIds = array_keys($blockedPlayerData);

                // Roster from team members
                $roster = ($selectedTeam?->users ?? collect())->map(
                    fn (User $player) => $this->buildPlayerData($player, $pivotMap, $selectedTeam, $season, $fixtures, $blockedPlayerData)
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
                        fn (User $player) => $this->buildPlayerData($player, $pivotMap, $selectedTeam, $season, $fixtures, $blockedPlayerData)
                    )->values();
                    $roster = $roster->concat($substitutes)->values();
                }
            }
        }

        // Modal data: pending change summary for the "Notify the team" modal
        $pendingAddedNames = [];
        $pendingRemovedNames = [];
        $modalIsComplete = true;

        if ($this->modalMessage && $this->selectedInterclubId) {
            $modalInterclub = $drawerInterclub ?? Interclub::find($this->selectedInterclubId);

            if ($modalInterclub) {
                $maxPlayers = $modalInterclub->total_players;
                $modalIsComplete = $modalInterclub->isSelectionComplete();
            }

            if ($this->isUpdateMode) {
                $pendingAddedNames = User::whereIn('id', $this->pendingAddedIds)
                    ->get()
                    ->map(fn (User $u) => $u->last_name . ' ' . $u->first_name)
                    ->values()
                    ->all();
                $pendingRemovedNames = User::whereIn('id', $this->pendingRemovedIds)
                    ->get()
                    ->map(fn (User $u) => $u->last_name . ' ' . $u->first_name)
                    ->values()
                    ->all();
            }
        }

        $searchResults = collect();
        $searchNote = null;
        if ($canSearchSubstitute && strlen($this->search) >= 2) {
            $selectedTeam = $teams->firstWhere('id', $this->selectedTeamId);
            $teamCategory = $selectedTeam?->league?->category;
            $veteranCutoff = $season ? $season->end_at->copy()->subYears(40) : null;
            $excludedIds = array_merge($this->selectedPlayerIds, $blockedPlayerIds);

            // Interclub-eligible members matching the name, before the category /
            // alignment filters — so we can explain what got silently removed (I2).
            // NA and non-competitive members are out of scope and never surface.
            $nameMatches = User::interclubEligible()
                ->where(fn ($q) => $q
                    ->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('last_name', 'like', '%' . $this->search . '%'))
                ->get();

            $matchesCategory = fn (User $u): bool => match ($teamCategory) {
                'MEN' => $u->gender === Gender::MEN,
                'WOMEN' => $u->gender === Gender::WOMEN,
                'VETERANS' => $veteranCutoff !== null && $u->birthdate !== null
                    && $u->birthdate->toDateString() <= $veteranCutoff->toDateString(),
                default => true,
            };

            $eligible = $nameMatches
                ->reject(fn (User $u) => in_array($u->id, $excludedIds, true))
                ->filter($matchesCategory);

            $searchResults = $eligible->take(8)->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->last_name . ' ' . $u->first_name,
                'rank' => $u->ranking ?? '—',
            ])->values();

            if ($searchResults->isEmpty()) {
                $searchNote = $this->buildSearchNote($nameMatches, $excludedIds, $matchesCategory, $teamCategory);
            }
        }

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
            'searchNote' => $searchNote,
            'drawerInterclub' => $drawerInterclub,
            'isAdminOrCommittee' => $isAdminOrCommittee,
            'canSearchSubstitute' => $canSearchSubstitute,
            'weekSummary' => $isAdminOrCommittee ? $this->buildWeekSummary($allTeamsForSummary, $fixtures) : null,
            'matchDayMap' => $matchDayMap,
            'filterChips' => $this->getFilterChips(),
            'pendingAddedNames' => $pendingAddedNames,
            'pendingRemovedNames' => $pendingRemovedNames,
            'modalIsComplete' => $modalIsComplete,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Captain Selection'));
    }

    /**
     * Mirrors the scoping of loadAccessibleTeams(): admins, committee members and
     * selectors reach every team, a captain only the teams they lead.
     */
    /**
     * Delegates to InterclubPolicy::selectLineup, so the rule lives in one place
     * rather than being restated here — this component and the policy answered
     * the same question in two different ways before.
     */
    private function authorizeInterclub(Interclub $interclub): void
    {
        Gate::authorize('selectLineup', $interclub);
    }

    /**
     * @param  Collection<int, mixed>  $pivotMap
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     * @param  array<int, string>  $blockedPlayerData  user_id => team_name already selected this week
     * @return array<string, mixed>
     */
    private function buildPlayerData(User $player, Collection $pivotMap, ?Team $team, ?Season $season, \Illuminate\Database\Eloquent\Collection $fixtures, array $blockedPlayerData = []): array
    {
        $pivot = $pivotMap->get($player->id);
        $avail = $pivot?->availability
            ? InterclubAvailability::from($pivot->availability)
            : null;

        $matchesPlayed = $season && $team
            ? $this->matchesPlayedCount($player->id, $team->id, $season, $fixtures)
            : 0;

        $matchesSelected = $season && $team
            ? $this->matchesSelectedCount($player->id, $team->id, $season, $fixtures)
            : 0;

        return [
            'id' => $player->id,
            'name' => $player->last_name . ' ' . $player->first_name,
            'last_name' => $player->last_name ?? '',
            'first_name' => $player->first_name ?? '',
            // Captain override (decision T8): a captain always sees their own
            // players' contact details on the selection screen, regardless of
            // the members' opt-in contact-visibility preferences.
            'phone_number' => $player->phone_number,
            'email' => $player->email,
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

    /**
     * Explain why a substitute search returned nothing: matching competitors do
     * exist, but the category rule and/or the same-week alignment hid them (I2).
     *
     * @param  Collection<int, User>  $nameMatches
     * @param  array<int, int>  $excludedIds
     * @param  callable(User): bool  $matchesCategory
     */
    private function buildSearchNote(Collection $nameMatches, array $excludedIds, callable $matchesCategory, ?string $teamCategory): ?string
    {
        if ($nameMatches->isEmpty()) {
            return null;
        }

        $hiddenByAlignment = $nameMatches->contains(fn (User $u) => in_array($u->id, $excludedIds, true));
        $hiddenByCategory = $nameMatches
            ->reject(fn (User $u) => in_array($u->id, $excludedIds, true))
            ->contains(fn (User $u) => ! $matchesCategory($u));

        $reasons = [];

        if ($hiddenByCategory) {
            $reasons[] = match ($teamCategory) {
                'MEN' => __('this team only lines up men'),
                'WOMEN' => __('this team only lines up women'),
                'VETERANS' => __('this team only lines up veterans (40 and over)'),
                default => null,
            };
        }

        if ($hiddenByAlignment) {
            $reasons[] = __('some are already selected here or lined up in another team this week');
        }

        $reasons = array_filter($reasons);

        if ($reasons === []) {
            return null;
        }

        return __('Some players match your search but are hidden: :reasons.', [
            'reasons' => implode(' ; ', $reasons),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     * @return array<string, mixed>
     */
    private function buildTeamData(Team $team, \Illuminate\Database\Eloquent\Collection $fixtures): array
    {
        $teamUserIds = $team->users->pluck('id');
        $teamMemberCount = $teamUserIds->count();

        $interclubs = $this->fixturesForTeam($fixtures, $team->id);

        $matches = $interclubs->map(function (Interclub $ic) use ($teamMemberCount) {
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

            $isPast = $ic->start_date_time < now();
            $daysUntil = (int) now()->diffInDays($ic->start_date_time, false);

            $status = $this->fixtureStatus($ic);

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
     * The team zoom is not applied here: it only dims the other rows, which
     * Alpine does client-side. Filtering server-side also emptied the team
     * chips, leaving no way back to another team without clearing the zoom.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Team>  $teams
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     */
    private function buildWeekSummary(\Illuminate\Database\Eloquent\Collection $teams, \Illuminate\Database\Eloquent\Collection $fixtures): array
    {
        $weekNumbers = $this->weekNumbers($teams, $fixtures);

        $weeks = $weekNumbers->map(fn (int $wk) => [
            'wk' => $wk,
            'status' => $this->weekStatus($wk, $teams, $fixtures),
        ])->values()->all();

        // A week everyone has already played is behind us, not prepared: it
        // leaves the score entirely rather than counting as ready. The score
        // therefore reads "ready out of what is left", and its denominator
        // shrinks as the season goes.
        $scored = collect($weeks)->reject(fn (array $w) => $w['status'] === 'past');

        $total = $scored->count();
        $ok = $scored->where('status', 'confirmed')->count();

        return [
            'weeks' => $weeks,
            'preparation_score' => $total > 0 ? (int) round($ok / $total * 100) : 0,
            'total' => $total,
            'ok' => $ok,
            'matrix' => $this->teamWeekMatrix($teams, $weekNumbers, $fixtures),
            'teams' => $teams->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name])->values()->all(),
        ];
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'MEN' => __('Men'),
            'WOMEN' => __('Women'),
            'VETERANS' => __('Veterans'),
            default => $category,
        };
    }

    /**
     * The status rule, in one place. Both the team cards and the preparation
     * matrix read it — they used to restate it separately and were free to
     * drift apart.
     */
    private function fixtureStatus(Interclub $interclub): string
    {
        if ($interclub->start_date_time < now()) {
            return 'past';
        }

        $users = $interclub->users;
        $maxPlayers = $interclub->total_players;

        $confirmedAtCount = $users->filter(fn ($u) => $u->registration?->is_selected && $u->registration?->selection_confirmed_at)->count();
        $selectedCount = $users->filter(fn ($u) => $u->registration?->is_selected)->count();
        $availableCount = $users->filter(fn ($u) => $u->registration?->availability === 'available')->count();
        $daysUntil = (int) now()->diffInDays($interclub->start_date_time, false);

        return match (true) {
            $confirmedAtCount > 0 => 'confirmed',
            $selectedCount >= $maxPlayers => 'actionable',
            $availableCount >= $maxPlayers => 'actionable',
            $daysUntil <= 14 => 'urgent',
            default => 'future',
        };
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     * @return \Illuminate\Database\Eloquent\Collection<int, Interclub>
     */
    private function fixturesForTeam(\Illuminate\Database\Eloquent\Collection $fixtures, int $teamId): \Illuminate\Database\Eloquent\Collection
    {
        return $fixtures->filter(fn (Interclub $ic) => $ic->visited_team_id === $teamId
            || $ic->visiting_team_id === $teamId)->values();
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

    /**
     * Every fixture the render needs, in one query. Both the team cards and the
     * preparation matrix filter this collection in memory instead of querying
     * per team and per cell.
     *
     * @param  array<int, int>  $teamIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Interclub>
     */
    private function loadFixtures(array $teamIds): \Illuminate\Database\Eloquent\Collection
    {
        if ($teamIds === []) {
            return Interclub::newModelInstance()->newCollection();
        }

        return Interclub::with(['visitedTeam.club', 'visitingTeam.club', 'users'])
            ->where(fn ($q) => $q
                ->whereIn('visited_team_id', $teamIds)
                ->orWhereIn('visiting_team_id', $teamIds))
            ->orderBy('start_date_time')
            ->orderBy('id')
            ->get();
    }

    /**
     * Memoised for the render: `with()` and `getFilterChips()` both need the
     * accessible teams and used to load them twice.
     *
     * @var array<string, \Illuminate\Database\Eloquent\Collection<int, Team>>
     */
    private array $accessibleTeamsCache = [];

    private function loadAccessibleTeams(User $user, ?Season $season): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = (string) ($season?->id ?? 'none');

        if (isset($this->accessibleTeamsCache[$cacheKey])) {
            return $this->accessibleTeamsCache[$cacheKey];
        }

        $query = Team::with(['league', 'club', 'captain', 'users'])->inClub();

        if ($season) {
            $query->where('season_id', $season->id);
        }

        // A club-wide selector sees every team; a captain, only theirs.
        if (! $user->can(Permission::SelectionsManage->value)) {
            $query->where('captain_id', $user->id);
        }

        return $this->accessibleTeamsCache[$cacheKey] = $query->orderBy('name')->get();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Interclub> $fixtures */
    private function matchesPlayedCount(int $userId, int $teamId, Season $season, \Illuminate\Database\Eloquent\Collection $fixtures): int
    {
        return $this->fixturesForTeam($fixtures, $teamId)
            ->filter(fn (Interclub $ic) => $ic->season_id === $season->id
                && $ic->start_date_time < now()
                && $ic->users->contains(fn (User $u) => $u->id === $userId && (bool) $u->registration?->has_played))
            ->count();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Interclub> $fixtures */
    private function matchesSelectedCount(int $userId, int $teamId, Season $season, \Illuminate\Database\Eloquent\Collection $fixtures): int
    {
        return $this->fixturesForTeam($fixtures, $teamId)
            ->filter(fn (Interclub $ic) => $ic->season_id === $season->id
                && $ic->users->contains(fn (User $u) => $u->id === $userId && (bool) $u->registration?->is_selected))
            ->count();
    }

    private function resetSendModal(): void
    {
        $this->modalMessage = false;
        $this->captainMeetupInfo = '';
        $this->isUpdateMode = false;
        $this->pendingAddedIds = [];
        $this->pendingRemovedIds = [];
    }

    private function selectedInterclub(): ?Interclub
    {
        if (! $this->selectedInterclubId) {
            return null;
        }

        $interclub = Interclub::find($this->selectedInterclubId);

        if ($interclub) {
            $this->authorizeInterclub($interclub);
        }

        return $interclub;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Team>  $teams
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     * @return array<int, array<int, string|null>>
     */
    private function teamWeekMatrix(\Illuminate\Database\Eloquent\Collection $teams, Collection $weekNumbers, \Illuminate\Database\Eloquent\Collection $fixtures): array
    {
        $matrix = [];

        foreach ($teams as $team) {
            $teamFixtures = $this->fixturesForTeam($fixtures, $team->id);
            $matrix[$team->id] = [];

            foreach ($weekNumbers as $wk) {
                $matrix[$team->id][$wk] = $teamFixtures->firstWhere('week_number', $wk)
                    ? $this->weekStatus($wk, Team::newModelInstance()->newCollection([$team]), $fixtures)
                    : null;
            }
        }

        return $matrix;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Team>  $teams
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     */
    private function weekNumbers(\Illuminate\Database\Eloquent\Collection $teams, \Illuminate\Database\Eloquent\Collection $fixtures): Collection
    {
        $teamIds = $teams->pluck('id')->all();

        return $fixtures
            ->filter(fn (Interclub $ic) => in_array($ic->visited_team_id, $teamIds, true)
                || in_array($ic->visiting_team_id, $teamIds, true))
            ->reject(fn (Interclub $ic) => $ic->week_number === null)
            ->pluck('week_number')
            ->unique()
            ->values();
    }

    /**
     * Worst status across the teams playing that week. A week where every
     * fixture has been played reports 'past' so it can leave the preparation
     * score rather than inflate it.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Team>  $teams
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     */
    private function weekStatus(int $weekNumber, \Illuminate\Database\Eloquent\Collection $teams, \Illuminate\Database\Eloquent\Collection $fixtures): string
    {
        $liveStatus = null;
        $sawPlayedFixture = false;

        foreach ($teams as $team) {
            // Fixtures are ordered by kick-off, so a team playing twice in one
            // week is rated on its earliest fixture. The query this replaced
            // had no ORDER BY and picked whichever row the engine returned.
            $interclub = $this->fixturesForTeam($fixtures, $team->id)
                ->firstWhere('week_number', $weekNumber);

            if (! $interclub) {
                continue;
            }

            $status = $this->fixtureStatus($interclub);

            if ($status === 'past') {
                $sawPlayedFixture = true;

                continue;
            }

            $liveStatus = $this->worstOf($liveStatus ?? 'confirmed', $status);
        }

        // A single fixture still to play decides the week; 'past' is reserved
        // for weeks where there is nothing left to prepare.
        if ($liveStatus !== null) {
            return $liveStatus;
        }

        return $sawPlayedFixture ? 'past' : 'confirmed';
    }

    /**
     * Ordered by how much attention the week needs. A distant fixture with
     * nothing done asks less of a selector than one that could be composed
     * right now, so 'actionable' outranks 'future' — it used to be the other
     * way round, and a week with real work to do showed up as quiet.
     */
    private function worstOf(string $a, string $b): string
    {
        $rank = ['confirmed' => 0, 'future' => 1, 'actionable' => 2, 'urgent' => 3];

        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }
};
