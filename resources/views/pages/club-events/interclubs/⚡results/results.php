<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs;

use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast;

    // Modal state
    public bool $deleteModal = false;

    // Delete confirmation
    public ?int $deletingInterclubResultId = null;

    // Form fields
    public ?int $editingInterclubResultId = null;

    public ?string $editingTeamCategory = null;

    public ?int $editingTeamId = null;

    public bool $editModal = false;

    // Team forfait général
    public ?int $forfeitingTeamId = null;

    public bool $isHome = true;

    public ?string $matchDate = null;

    public string $matchType = 'normal';

    public ?string $opponentName = null;

    public ?int $scoreThem = null;

    public ?int $scoreUs = null;

    public ?int $seasonId = null;

    public bool $teamForfeitModal = false;

    public function clearFilters(): void
    {
        $this->seasonId = Season::current()?->id;
    }

    public function confirmDelete(int $matchResultId): void
    {
        $this->deletingInterclubResultId = $matchResultId;
        $this->deleteModal = true;
    }

    public function declareTeamForfeit(): void
    {
        $this->authorizeTeam($this->forfeitingTeamId);

        InterclubResult::where('team_id', $this->forfeitingTeamId)
            ->where('season_id', $this->seasonId)
            ->whereNull('result')
            ->where('is_bye', false)
            ->update(['result' => InterclubResultEnum::WITHDRAWAL->value]);

        $this->teamForfeitModal = false;
        $this->forfeitingTeamId = null;
        $this->warning(__('General forfeit declared for this team.'));
    }

    public function delete(): void
    {
        if ($this->deletingInterclubResultId) {
            $matchResult = InterclubResult::findOrFail($this->deletingInterclubResultId);
            $this->authorizeTeam($matchResult->team_id);
            $matchResult->delete();
            $this->success(__('Match deleted'));
        }

        $this->deleteModal = false;
        $this->deletingInterclubResultId = null;
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->seasonId !== Season::current()?->id) {
            $seasonName = Season::find($this->seasonId)?->name ?? __('All seasons');
            $chips[] = ['key' => 'seasonId', 'label' => __('Season') . ': ' . $seasonName];
        }

        return $chips;
    }

    public function maxPoints(): int
    {
        return $this->editingTeamCategory === LeagueCategory::MEN->name ? 16 : 10;
    }

    public function mount(): void
    {
        $this->seasonId = Season::current()?->id;

        // Results delegate, or captain of at least one team; authorizeTeam()
        // then narrows each mutation to the teams actually captained.
        Gate::authorize('access-results');
    }

    public function openEditModal(int $matchResultId): void
    {
        $mr = InterclubResult::findOrFail($matchResultId);
        $this->resetErrorBag();
        $this->editingInterclubResultId = $mr->id;
        $this->editingTeamId = $mr->team_id;
        $this->matchDate = $mr->match_date?->format('Y-m-d');
        $this->isHome = $mr->is_home;
        $this->opponentName = $mr->opponent_name;

        $this->editingTeamCategory = Team::with('league')->find($mr->team_id)?->league?->category;

        $this->scoreUs = null;
        $this->scoreThem = null;
        if ($mr->score && str_contains($mr->score, '-')) {
            [$home, $away] = array_map('intval', explode('-', $mr->score, 2));
            $this->scoreUs = $this->isHome ? $home : $away;
            $this->scoreThem = $this->isHome ? $away : $home;
        }

        $this->matchType = match (true) {
            $mr->is_bye => 'bye',
            $mr->result === InterclubResultEnum::FORFEIT_WIN => 'forfeit_opponent',
            $mr->result === InterclubResultEnum::WITHDRAWAL_OPPONENT => 'forfeit_general_opponent',
            $mr->result === InterclubResultEnum::FORFEIT_LOSS => 'forfeit_us',
            $mr->result === InterclubResultEnum::WITHDRAWAL => 'forfeit_general_us',
            default => 'normal',
        };
        $this->editModal = true;
    }

    public function openTeamForfeitModal(int $teamId): void
    {
        $this->forfeitingTeamId = $teamId;
        $this->teamForfeitModal = true;
    }

    public function removeFilter(string $key): void
    {
        if ($key === 'seasonId') {
            $this->seasonId = Season::current()?->id;

            return;
        }

        $this->reset([$key]);
    }

    public function render(): View
    {
        return $this->view()->title(__('Results'));
    }

    public function rules(): array
    {
        $maxPoints = $this->maxPoints();

        $scoreRule = $this->matchType === 'normal'
            ? ['nullable', 'integer', 'min:0', "max:{$maxPoints}"]
            : ['nullable'];

        $scoreSumRule = $this->matchType === 'normal'
            ? [
                'nullable', 'integer', 'min:0', "max:{$maxPoints}",
                function (string $attribute, mixed $value, \Closure $fail) use ($maxPoints): void {
                    if ($this->scoreUs !== null && $this->scoreThem !== null) {
                        if ($this->scoreUs + $this->scoreThem !== $maxPoints) {
                            $fail(__('The total score must equal :max points.', ['max' => $maxPoints]));
                        }
                    }
                },
            ]
            : ['nullable'];

        return [
            'matchType' => ['required', Rule::in(['normal', 'bye', 'forfeit_opponent', 'forfeit_general_opponent', 'forfeit_us', 'forfeit_general_us'])],
            'scoreUs' => $scoreRule,
            'scoreThem' => $scoreSumRule,
        ];
    }

    public function save(): void
    {
        $this->validate();
        $this->authorizeTeam($this->editingTeamId);

        $isBye = $this->matchType === 'bye';

        $scoreString = null;
        if ($this->matchType === 'normal' && $this->scoreUs !== null && $this->scoreThem !== null) {
            $scoreString = $this->isHome
                ? "{$this->scoreUs}-{$this->scoreThem}"
                : "{$this->scoreThem}-{$this->scoreUs}";
        }

        $result = match ($this->matchType) {
            'bye' => null,
            'forfeit_opponent' => InterclubResultEnum::FORFEIT_WIN,
            'forfeit_general_opponent' => InterclubResultEnum::WITHDRAWAL_OPPONENT,
            'forfeit_us' => InterclubResultEnum::FORFEIT_LOSS,
            'forfeit_general_us' => InterclubResultEnum::WITHDRAWAL,
            default => $this->resultFromScore(),
        };

        $data = [
            'team_id' => $this->editingTeamId,
            'season_id' => $this->seasonId,
            'match_date' => $isBye ? null : $this->matchDate,
            'week_number' => ($isBye || ! $this->matchDate) ? null : Carbon::parse($this->matchDate)->isoWeek(),
            'is_home' => $this->isHome,
            'opponent_name' => in_array($this->matchType, ['bye', 'forfeit_general_us']) ? null : $this->opponentName,
            'score' => $scoreString,
            'result' => $result?->value,
            'is_bye' => $isBye,
        ];

        InterclubResult::findOrFail($this->editingInterclubResultId)->update($data);

        if ($this->matchType === 'normal' && $this->matchDate) {
            $interclub = Interclub::where('season_id', $this->seasonId)
                ->where(fn ($q) => $q->where('visited_team_id', $this->editingTeamId)
                    ->orWhere('visiting_team_id', $this->editingTeamId))
                ->whereDate('start_date_time', $this->matchDate)
                ->first();

            $interclub?->users()
                ->wherePivot('is_selected', true)
                ->each(fn ($u) => $interclub->users()->updateExistingPivot($u->id, ['has_played' => true]));
        }

        $this->success(__('Match updated'));

        $this->editModal = false;
    }

    public function updateFinalPosition(int $teamId, ?string $position): void
    {
        $this->authorizeTeam($teamId);
        Team::findOrFail($teamId)->update(['final_position' => $position ?: null]);
    }

    public function with(): array
    {
        $user = Auth::user();
        $categoryOrder = [
            LeagueCategory::MEN->name => 0,
            LeagueCategory::WOMEN->name => 1,
            LeagueCategory::VETERANS->name => 2,
        ];

        $teamsQuery = Team::with([
            'league',
            'interclubResults' => fn ($q) => $q->where('season_id', $this->seasonId)->orderBy('match_date'),
        ])
            ->inClub()
            ->where('season_id', $this->seasonId);

        if (! $user->can(Permission::ResultsManage->value)) {
            $teamsQuery->where('captain_id', $user->id);
        }

        $teams = $teamsQuery->get()
            ->sortBy(fn (Team $t): int => $categoryOrder[$t->league?->category] ?? 99);

        $stats = $teams->mapWithKeys(fn (Team $team): array => [
            $team->id => $this->computeStats($team->interclubResults),
        ]);

        $teamsByCategory = $teams->groupBy(fn (Team $t) => $t->league?->category ?? LeagueCategory::MEN->name);

        $matchDayMap = $this->seasonId ? Interclub::matchDayMap($this->seasonId) : [];

        return [
            'filterChips' => $this->filterChips,
            'seasons' => Season::orderBy('start_at')->get(),
            'teamsByCategory' => $teamsByCategory,
            'stats' => $stats,
            'matchDayMap' => $matchDayMap,
            'matchTypeOptions' => [
                ['value' => 'normal',                   'label' => __('Normal')],
                ['value' => 'forfeit_opponent',         'label' => __('Forfait adverse')],
                ['value' => 'forfeit_general_opponent', 'label' => __('Opponent general forfeit')],
                ['value' => 'forfeit_us',               'label' => __('Notre forfait')],
                ['value' => 'forfeit_general_us',       'label' => __('Our general forfeit')],
                ['value' => 'bye',                      'label' => __('Bye')],
            ],
            'breadcrumbs' => Breadcrumb::make()->home()->add('Interclubs', '#')->results()->toArray(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Results'));
    }

    /**
     * A club-wide manager records any team's result; a captain only their own.
     */
    private function authorizeTeam(int $teamId): void
    {
        if (Auth::user()->can(Permission::ResultsManage->value)) {
            return;
        }

        abort_unless(Team::where('id', $teamId)->where('captain_id', Auth::id())->exists(), 403);
    }

    private function computeStats(Collection $interclubResults): array
    {
        $real = $interclubResults->where('is_bye', false)->filter(fn ($mr): bool => $mr->result !== null);
        $played = $real->count();
        $wins = $real->filter(fn ($mr): bool => in_array($mr->result, [InterclubResultEnum::WIN, InterclubResultEnum::FORFEIT_WIN]))->count();
        $losses = $real->filter(fn ($mr): bool => in_array($mr->result, [InterclubResultEnum::LOSS, InterclubResultEnum::FORFEIT_LOSS]))->count();
        $draws = $real->filter(fn ($mr): bool => $mr->result === InterclubResultEnum::DRAW)->count();

        return [
            'played' => $played,
            'wins' => $wins,
            'losses' => $losses,
            'draws' => $draws,
            'win_rate' => $played > 0 ? (int) round($wins / $played * 100) : 0,
        ];
    }

    private function resultFromScore(): ?InterclubResultEnum
    {
        if ($this->scoreUs === null || $this->scoreThem === null) {
            return null;
        }

        return match (true) {
            $this->scoreUs > $this->scoreThem => InterclubResultEnum::WIN,
            $this->scoreUs < $this->scoreThem => InterclubResultEnum::LOSS,
            default => InterclubResultEnum::DRAW,
        };
    }
};
