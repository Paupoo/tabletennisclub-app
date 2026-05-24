<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\ControlCenter;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Services\InterclubAvailabilityService;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public bool $drawerSelection = false;

    public bool $filterAlerts = false;

    public bool $modalMessage = false;

    public string $captainMessage = '';

    public ?int $drawerInterclubId = null;

    public ?int $selectedSeasonId = null;

    public ?int $selectedTeam = null;

    public ?int $selectedWeek = null;

    /** @var array<int, int> */
    public array $selectedPlayerIds = [];

    public string $search = '';

    public function mount(): void
    {
        $this->selectedSeasonId = Season::current()?->id;
    }

    public function updatedSelectedSeasonId(): void
    {
        $this->selectedWeek = null;
        $this->selectedTeam = null;
    }

    public function confirmAndSend(InterclubAvailabilityService $service): void
    {
        if (! $this->drawerInterclubId) {
            return;
        }

        $interclub = Interclub::findOrFail($this->drawerInterclubId);
        $service->confirmSelection($interclub, $this->captainMessage);

        $this->modalMessage = false;
        $this->captainMessage = '';

        $this->success(
            __('Selection confirmed!'),
            __('Players have received their invitation.'),
            icon: 'o-paper-airplane'
        );
    }

    public function nextWeek(): void
    {
        $weeks = $this->weekNumbersForSelectedSeason();
        $max = $weeks->last();
        if ($this->selectedWeek && $max && $this->selectedWeek < $max) {
            $next = $weeks->first(fn ($w) => $w > $this->selectedWeek);
            if ($next) {
                $this->selectedWeek = $next;
            }
        }
    }

    public function openSelection(int $interclubId): void
    {
        $this->drawerInterclubId = $interclubId;
        $interclub = Interclub::findOrFail($interclubId);

        $this->selectedPlayerIds = $interclub->users()
            ->wherePivot('is_selected', true)
            ->pluck('users.id')
            ->toArray();

        $this->drawerSelection = true;
    }

    public function prevWeek(): void
    {
        $weeks = $this->weekNumbersForSelectedSeason();
        $min = $weeks->first();
        if ($this->selectedWeek && $min && $this->selectedWeek > $min) {
            $prev = $weeks->last(fn ($w) => $w < $this->selectedWeek);
            if ($prev) {
                $this->selectedWeek = $prev;
            }
        }
    }

    public function render(): View
    {
        return $this->view();
    }

    public function saveSelection(): void
    {
        if (! $this->drawerInterclubId) {
            return;
        }

        $interclub = Interclub::findOrFail($this->drawerInterclubId);
        $existingIds = $interclub->users()->pluck('users.id')->toArray();

        foreach ($this->selectedPlayerIds as $userId) {
            if (in_array($userId, $existingIds)) {
                $interclub->users()->updateExistingPivot($userId, ['is_selected' => true]);
            } else {
                $interclub->users()->attach($userId, ['is_selected' => true]);
            }
        }

        foreach (array_diff($existingIds, $this->selectedPlayerIds) as $userId) {
            $interclub->users()->updateExistingPivot($userId, ['is_selected' => false]);
        }

        $this->drawerSelection = false;
        $this->modalMessage = true;
    }

    public function togglePlayer(int $userId): void
    {
        $interclub = $this->drawerInterclubId ? Interclub::find($this->drawerInterclubId) : null;
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
        $seasons = Season::orderBy('start_at')->get();
        $season = $this->selectedSeasonId
            ? $seasons->firstWhere('id', $this->selectedSeasonId)
            : Season::current();

        $allTeams = Team::with(['league', 'captain', 'users', 'club'])
            ->inClub()
            ->when($season, fn ($q) => $q->where('season_id', $season->id))
            ->get();

        $weekNumbers = $this->buildWeekNumbers($allTeams);

        if ($this->selectedWeek === null && $weekNumbers->isNotEmpty()) {
            $this->selectedWeek = $weekNumbers
                ->filter(fn ($w) => $w >= now()->isoWeek)
                ->first() ?? $weekNumbers->first();
        }

        $currentWeek = $this->selectedWeek;

        $weeksMonitor = $weekNumbers->map(function (int $wk) use ($allTeams): array {
            $status = $this->computeWeekStatus($wk, $allTeams);

            return ['wk' => $wk, 'status' => $status];
        });

        $headers = [
            ['key' => 'name', 'label' => __('Team'), 'class' => 'w-72'],
            ['key' => 'captain', 'label' => __('Captain')],
            ['key' => 'players', 'label' => __('Players'), 'class' => 'text-center'],
            ['key' => 'status', 'label' => __('Status'), 'class' => 'text-center'],
            ['key' => 'action', 'label' => ''],
        ];

        $rawTeams = $allTeams->map(function (Team $team) use ($currentWeek): array {
            $interclub = $currentWeek ? $this->getTeamInterclubForWeek($team, $currentWeek) : null;
            $maxPlayers = $team->league?->category === 'MEN' ? 4 : 3;
            $selectedCount = $interclub
                ? $interclub->users()->wherePivot('is_selected', true)->count()
                : 0;

            $status = match (true) {
                $interclub === null => 'no_match',
                $selectedCount >= $maxPlayers => 'validated',
                $selectedCount > 0 => 'pending',
                default => 'alert',
            };

            return [
                'id' => $interclub?->id,
                'team_id' => $team->id,
                'name' => $team->name,
                'div' => $team->league?->division ?? '—',
                'captain' => $team->captain?->last_name . ' ' . ($team->captain?->first_name ?? ''),
                'category' => match ($team->league?->category) {
                    'MEN' => __('Men'),
                    'WOMEN' => __('Women'),
                    'VETERANS' => __('Veterans'),
                    default => __('Other'),
                },
                'players' => $selectedCount,
                'max_players' => $maxPlayers,
                'status' => $status,
            ];
        });

        $categories = $rawTeams
            ->when($this->selectedTeam, fn ($c) => $c->where('team_id', $this->selectedTeam))
            ->filter(fn ($t) => ! $this->filterAlerts || $t['status'] === 'alert')
            ->filter(fn ($t) => $t['status'] !== 'no_match')
            ->groupBy('category');

        $searchResults = [];
        if (strlen($this->search) >= 2 && $this->drawerInterclubId) {
            $searchResults = User::where('is_competitor', true)
                ->where(function ($q): void {
                    $q->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%');
                })
                ->whereNotIn('id', $this->selectedPlayerIds)
                ->limit(8)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->last_name . ' ' . $u->first_name,
                    'rank' => $u->ranking ?? '—',
                ]);
        }

        $drawerInterclub = $this->drawerInterclubId ? Interclub::with(['visitedTeam.users', 'visitingTeam.users'])->find($this->drawerInterclubId) : null;
        $drawerTeam = $drawerInterclub
            ? ($drawerInterclub->visitedTeam?->club?->licence === config('app.club_licence')
                ? $drawerInterclub->visitedTeam
                : $drawerInterclub->visitingTeam)
            : null;

        $drawerRoster = $drawerTeam ? $drawerTeam->users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->last_name . ' ' . $u->first_name,
            'rank' => $u->ranking ?? '—',
        ]) : collect();

        $totalWeeks = $weekNumbers->count();
        $completedWeeks = $weeksMonitor->where('status', 'ok')->count();
        $preparationScore = $totalWeeks > 0 ? round($completedWeeks / $totalWeeks * $totalWeeks) : 0;

        $matchDayMap = $season ? Interclub::matchDayMap($season->id) : [];

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Interclubs Control Center'))
                ->toArray(),
            'headers' => $headers,
            'categories' => $categories,
            'seasons_list' => $seasons->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'current_season' => $season,
            'weeks_options' => $weekNumbers->map(fn ($w) => ['id' => $w, 'name' => 'S' . ($matchDayMap[$w] ?? $w)]),
            'weeks_monitor' => $weeksMonitor,
            'teams_list' => $allTeams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
            'preparation_score' => $preparationScore,
            'total_weeks' => $totalWeeks,
            'drawerInterclub' => $drawerInterclub,
            'drawerRoster' => $drawerRoster,
            'drawerMaxPlayers' => $drawerTeam?->league?->category === 'MEN' ? 4 : 3,
            'searchResults' => $searchResults,
            'matchDayMap' => $matchDayMap,
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
    private function buildWeekNumbers(\Illuminate\Database\Eloquent\Collection $teams): Collection
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
    private function computeWeekStatus(int $weekNumber, \Illuminate\Database\Eloquent\Collection $teams): string
    {
        $worstStatus = 'ok';

        foreach ($teams as $team) {
            $interclub = $this->getTeamInterclubForWeek($team, $weekNumber);

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

    private function getTeamInterclubForWeek(Team $team, int $weekNumber): ?Interclub
    {
        return Interclub::where('week_number', $weekNumber)
            ->where(function ($q) use ($team): void {
                $q->where('visited_team_id', $team->id)
                    ->orWhere('visiting_team_id', $team->id);
            })
            ->first();
    }

    private function weekNumbersForSelectedSeason(): Collection
    {
        $season = $this->selectedSeasonId
            ? Season::find($this->selectedSeasonId)
            : Season::current();

        $teams = Team::inClub()
            ->when($season, fn ($q) => $q->where('season_id', $season->id))
            ->get();

        return $this->buildWeekNumbers($teams);
    }
};
