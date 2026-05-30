<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs;

use App\Domains\Shared\Enums\InterclubResult;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\MatchResult;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;

    // Modal state
    public bool $deleteModal = false;

    // Delete confirmation
    public ?int $deletingMatchResultId = null;

    // Form fields
    public ?int $editingMatchResultId = null;

    public ?int $editingTeamId = null;

    public bool $editModal = false;

    // Team forfait général
    public ?int $forfeitingTeamId = null;

    public bool $isHome = true;

    public ?string $matchDate = null;

    public string $matchType = 'normal';

    public ?string $opponentName = null;

    public ?int $scoreUs = null;

    public ?int $scoreThem = null;

    public ?string $editingTeamCategory = null;

    public ?int $seasonId = null;

    public bool $teamForfeitModal = false;

    public function confirmDelete(int $matchResultId): void
    {
        $this->deletingMatchResultId = $matchResultId;
        $this->deleteModal = true;
    }

    public function declareTeamForfeit(): void
    {
        $this->authorizeTeam($this->forfeitingTeamId);

        MatchResult::where('team_id', $this->forfeitingTeamId)
            ->where('season_id', $this->seasonId)
            ->whereNull('result')
            ->where('is_bye', false)
            ->update(['result' => InterclubResult::WITHDRAWAL->value]);

        $this->teamForfeitModal = false;
        $this->forfeitingTeamId = null;
        $this->warning(__('General forfeit declared for this team.'));
    }

    public function delete(): void
    {
        if ($this->deletingMatchResultId) {
            $matchResult = MatchResult::findOrFail($this->deletingMatchResultId);
            $this->authorizeTeam($matchResult->team_id);
            $matchResult->delete();
            $this->success(__('Match deleted'));
        }

        $this->deleteModal = false;
        $this->deletingMatchResultId = null;
    }

    public function mount(): void
    {
        $this->seasonId = Season::current()?->id;

        $user = Auth::user();
        if (! $user->is_admin && ! $user->is_committee_member) {
            abort_unless(Team::where('captain_id', $user->id)->exists(), 403);
        }
    }

    public function openEditModal(int $matchResultId): void
    {
        $mr = MatchResult::findOrFail($matchResultId);
        $this->resetErrorBag();
        $this->editingMatchResultId = $mr->id;
        $this->editingTeamId = $mr->team_id;
        $this->matchDate = $mr->match_date?->format('Y-m-d');
        $this->isHome = $mr->is_home;
        $this->opponentName = $mr->opponent_name;

        $this->editingTeamCategory = Team::with('league')->find($mr->team_id)?->league?->category;

        $this->scoreUs = null;
        $this->scoreThem = null;
        if ($mr->score && str_contains($mr->score, '-')) {
            [$home, $away] = array_map('intval', explode('-', $mr->score, 2));
            $this->scoreUs   = $this->isHome ? $home : $away;
            $this->scoreThem = $this->isHome ? $away : $home;
        }

        $this->matchType = match (true) {
            $mr->is_bye => 'bye',
            $mr->result === InterclubResult::FORFEIT_WIN => 'forfeit_opponent',
            $mr->result === InterclubResult::WITHDRAWAL_OPPONENT => 'forfeit_general_opponent',
            $mr->result === InterclubResult::FORFEIT_LOSS => 'forfeit_us',
            $mr->result === InterclubResult::WITHDRAWAL => 'forfeit_general_us',
            default => 'normal',
        };
        $this->editModal = true;
    }

    public function openTeamForfeitModal(int $teamId): void
    {
        $this->forfeitingTeamId = $teamId;
        $this->teamForfeitModal = true;
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Results"));
    }

        public function render(): View
    {
        return $this->view()->title(__('Results'));
    }

    public function maxPoints(): int
    {
        return $this->editingTeamCategory === LeagueCategory::MEN->name ? 16 : 10;
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
                            $fail(__("Le score total doit être égal à :max points.", ['max' => $maxPoints]));
                        }
                    }
                },
            ]
            : ['nullable'];

        return [
            'matchType' => ['required', Rule::in(['normal', 'bye', 'forfeit_opponent', 'forfeit_general_opponent', 'forfeit_us', 'forfeit_general_us'])],
            'scoreUs'   => $scoreRule,
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
            'forfeit_opponent' => InterclubResult::FORFEIT_WIN,
            'forfeit_general_opponent' => InterclubResult::WITHDRAWAL_OPPONENT,
            'forfeit_us' => InterclubResult::FORFEIT_LOSS,
            'forfeit_general_us' => InterclubResult::WITHDRAWAL,
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

        MatchResult::findOrFail($this->editingMatchResultId)->update($data);

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
            'matchResults' => fn ($q) => $q->where('season_id', $this->seasonId)->orderBy('match_date'),
        ])
            ->inClub()
            ->where('season_id', $this->seasonId);

        if (! $user->is_admin && ! $user->is_committee_member) {
            $teamsQuery->where('captain_id', $user->id);
        }

        $teams = $teamsQuery->get()
            ->sortBy(fn (Team $t) => $categoryOrder[$t->league?->category] ?? 99);

        $stats = $teams->mapWithKeys(fn (Team $team) => [
            $team->id => $this->computeStats($team->matchResults),
        ]);

        $teamsByCategory = $teams->groupBy(fn (Team $t) => $t->league?->category ?? LeagueCategory::MEN->name);

        $matchDayMap = $this->seasonId ? Interclub::matchDayMap($this->seasonId) : [];

        return [
            'seasons' => Season::orderBy('start_at')->get(),
            'teamsByCategory' => $teamsByCategory,
            'stats' => $stats,
            'matchDayMap' => $matchDayMap,
            'matchTypeOptions' => [
                ['value' => 'normal',                   'label' => __('Normal')],
                ['value' => 'forfeit_opponent',         'label' => __('Forfait adverse')],
                ['value' => 'forfeit_general_opponent', 'label' => __('Forfait général adverse')],
                ['value' => 'forfeit_us',               'label' => __('Notre forfait')],
                ['value' => 'forfeit_general_us',       'label' => __('Notre forfait général')],
                ['value' => 'bye',                      'label' => __('Bye')],
            ],
            'breadcrumbs' => Breadcrumb::make()->home()->add('Interclubs', '#')->results()->toArray(),
        ];
    }

    private function authorizeTeam(int $teamId): void
    {
        $user = Auth::user();
        if ($user->is_admin || $user->is_committee_member) {
            return;
        }
        abort_unless(Team::where('id', $teamId)->where('captain_id', $user->id)->exists(), 403);
    }

    private function computeStats(Collection $matchResults): array
    {
        $real = $matchResults->where('is_bye', false)->filter(fn ($mr) => $mr->result !== null);
        $played = $real->count();
        $wins = $real->filter(fn ($mr) => in_array($mr->result, [InterclubResult::WIN, InterclubResult::FORFEIT_WIN]))->count();
        $losses = $real->filter(fn ($mr) => in_array($mr->result, [InterclubResult::LOSS, InterclubResult::FORFEIT_LOSS]))->count();
        $draws = $real->filter(fn ($mr) => $mr->result === InterclubResult::DRAW)->count();

        return [
            'played' => $played,
            'wins' => $wins,
            'losses' => $losses,
            'draws' => $draws,
            'win_rate' => $played > 0 ? (int) round($wins / $played * 100) : 0,
        ];
    }

    private function resultFromScore(): ?InterclubResult
    {
        if ($this->scoreUs === null || $this->scoreThem === null) {
            return null;
        }

        return match (true) {
            $this->scoreUs > $this->scoreThem => InterclubResult::WIN,
            $this->scoreUs < $this->scoreThem => InterclubResult::LOSS,
            default => InterclubResult::DRAW,
        };
    }
};
