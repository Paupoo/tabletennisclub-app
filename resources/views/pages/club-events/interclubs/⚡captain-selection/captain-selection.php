<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\CaptainSelection;

use App\Domains\Shared\Enums\InterclubAvailability;
use App\Models\ClubAdmin\Users\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubAvailabilityService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;

    public string $captainMeetupInfo = '';

    public bool $drawerSelection = false;

    public bool $modalMessage = false;

    public ?int $selectedSeasonId = null;

    public ?int $selectedInterclubId = null;

    public ?int $selectedTeamId = null;

    public string $search = '';

    /** @var array<int, int> */
    public array $selectedPlayerIds = [];

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
        if (! $user->is_admin && ! $user->is_committee_member) {
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
        } else {
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
    }

    public function with(): array
    {
        $user = Auth::user();
        $isAdminOrCommittee = $user->is_admin || $user->is_committee_member;

        $seasons = Season::orderBy('start_at')->get();
        $season = $this->selectedSeasonId
            ? $seasons->firstWhere('id', $this->selectedSeasonId)
            : Season::current();

        $teams = $this->loadAccessibleTeams($user, $season);

        $teamsData = $teams->map(fn (Team $team) => $this->buildTeamData($team, $season));

        $alertMatches = $teamsData
            ->flatMap(fn ($t) => collect($t['matches'])->map(fn ($m) => array_merge($m, ['team_name' => $t['name'], 'team_id' => $t['id']])))
            ->filter(fn ($m) => $m['alert'])
            ->values();

        // Drawer data: roster for the selected match
        $drawerInterclub = null;
        $roster = collect();
        $maxPlayers = 4;

        if ($this->selectedInterclubId && $this->drawerSelection) {
            $drawerInterclub = Interclub::find($this->selectedInterclubId);

            if ($drawerInterclub) {
                $selectedTeam = $teams->firstWhere('id', $this->selectedTeamId);
                $maxPlayers = $drawerInterclub->total_players;

                $pivotMap = $drawerInterclub->users()
                    ->get()
                    ->keyBy('id')
                    ->map(fn ($u) => $u->registration);

                $roster = ($selectedTeam?->users ?? collect())->map(
                    fn (User $player) => $this->buildPlayerData($player, $pivotMap, $selectedTeam, $season)
                )->sortBy([
                    ['rank_sort', 'asc'],
                    ['last_name', 'asc'],
                    ['first_name', 'asc'],
                ])->values();
            }
        }

        $searchResults = collect();
        if (strlen($this->search) >= 2) {
            $searchResults = User::where('is_competitor', true)
                ->where(fn ($q) => $q
                    ->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('last_name', 'like', '%' . $this->search . '%'))
                ->whereNotIn('id', $this->selectedPlayerIds)
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

        $matchDayMap = $season ? Interclub::matchDayMap($season->id) : [];

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'seasons_list' => $seasons->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'teams_list' => $teams->map(fn ($t) => ['id' => $t->id, 'name' => ($t->club?->name ?? '?') . ' ' . $t->name]),
            'teamsData' => $teamsData,
            'alertMatches' => $alertMatches,
            'roster' => $roster,
            'maxPlayers' => $maxPlayers,
            'searchResults' => $searchResults,
            'drawerInterclub' => $drawerInterclub,
            'isAdminOrCommittee' => $isAdminOrCommittee,
            'weekSummary' => $isAdminOrCommittee ? $this->buildWeekSummary($allTeamsForSummary) : null,
            'matchDayMap' => $matchDayMap,
        ];
    }

    /** @return array<string, mixed> */
    private function buildTeamData(Team $team, ?Season $season): array
    {
        $ourClubLicence = config('app.club_licence');
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

        $matches = $interclubs->map(function (Interclub $ic) use ($team, $teamMemberCount, $ourClubLicence) {
            $ourTeam = $ic->visitedTeam?->club?->licence === $ourClubLicence
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
                $selectedCount >= $ic->total_players => 'ready',
                $selectedCount > 0 => 'pending',
                default => 'future',
            };

            $alert = ! $isPast && $daysUntil <= 14 && $availableCount < $ic->total_players;

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
                'alert' => $alert,
                'selected_player_names' => $selectedPlayerNames,
            ];
        });

        return [
            'id' => $team->id,
            'name' => $team->name,
            'division' => $team->league?->division ?? '—',
            'captain_name' => trim(($team->captain?->last_name ?? '') . ' ' . ($team->captain?->first_name ?? '')),
            'matches' => $matches->values()->toArray(),
            'has_alert' => $matches->where('alert', true)->isNotEmpty(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $pivotMap
     * @return array<string, mixed>
     */
    private function buildPlayerData(User $player, Collection $pivotMap, Team $team, ?Season $season): array
    {
        $pivot = $pivotMap->get($player->id);
        $avail = $pivot?->availability
            ? InterclubAvailability::from($pivot->availability)
            : null;

        $matchesPlayed = $season
            ? $this->matchesPlayedCount($player->id, $team->id, $season)
            : 0;

        $matchesSelected = $season
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

        if (! $user->is_admin && ! $user->is_committee_member) {
            $query->where('captain_id', $user->id);
        }

        return $query->get();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
    private function buildWeekSummary(\Illuminate\Database\Eloquent\Collection $teams): array
    {
        $weekNumbers = $this->weekNumbers($teams);

        $weeks = $weekNumbers->map(fn (int $wk) => [
            'wk' => $wk,
            'status' => $this->weekStatus($wk, $teams),
        ])->values()->all();

        $total = $weekNumbers->count();
        $ok = collect($weeks)->where('status', 'ok')->count();

        return [
            'weeks' => $weeks,
            'preparation_score' => $total > 0 ? (int) round($ok / $total * 100) : 0,
            'total' => $total,
            'ok' => $ok,
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
    private function weekNumbers(\Illuminate\Database\Eloquent\Collection $teams): Collection
    {
        $teamIds = $teams->pluck('id');

        return Interclub::whereIn('visited_team_id', $teamIds)
            ->orWhereIn('visiting_team_id', $teamIds)
            ->whereNotNull('week_number')
            ->pluck('week_number')
            ->unique()
            ->sort()
            ->values();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
    private function weekStatus(int $weekNumber, \Illuminate\Database\Eloquent\Collection $teams): string
    {
        $worstStatus = 'ok';

        foreach ($teams as $team) {
            $interclub = Interclub::where('week_number', $weekNumber)
                ->where(fn ($q) => $q
                    ->where('visited_team_id', $team->id)
                    ->orWhere('visiting_team_id', $team->id))
                ->first();

            if (! $interclub) {
                continue;
            }

            $maxPlayers = $team->league?->category === 'MEN' ? 4 : 3;
            $selectedCount = $interclub->users()->wherePivot('is_selected', true)->count();

            if ($selectedCount === 0) {
                return 'nok';
            }

            if ($selectedCount < $maxPlayers) {
                $worstStatus = 'warning';
            }
        }

        return $worstStatus;
    }

    private function selectedInterclub(): ?Interclub
    {
        if (! $this->selectedInterclubId) {
            return null;
        }

        return Interclub::find($this->selectedInterclubId);
    }
};
