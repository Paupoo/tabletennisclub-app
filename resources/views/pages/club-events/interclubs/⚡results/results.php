<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs;

use App\Enums\InterclubResult;
use App\Enums\LeagueCategory;
use App\Models\ClubEvents\Interclub\MatchResult;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

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

    public ?string $score = null;

    public ?int $seasonId = null;

    public bool $teamForfeitModal = false;

    public function confirmDelete(int $matchResultId): void
    {
        $this->deletingMatchResultId = $matchResultId;
        $this->deleteModal = true;
    }

    public function declareTeamForfeit(): void
    {
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
            MatchResult::findOrFail($this->deletingMatchResultId)->delete();
            $this->success(__('Match deleted'));
        }

        $this->deleteModal = false;
        $this->deletingMatchResultId = null;
    }

    public function mount(): void
    {
        $this->seasonId = Season::current()?->id;
    }

    public function openAddModal(int $teamId): void
    {
        $this->resetErrorBag();
        $this->editingMatchResultId = null;
        $this->editingTeamId = $teamId;
        $this->matchType = 'normal';
        $this->matchDate = null;
        $this->isHome = true;
        $this->opponentName = null;
        $this->score = null;
        $this->editModal = true;
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
        $this->score = $mr->score;
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

    public function render(): View
    {
        return $this->view()->title(__('Results'));
    }

    public function rules(): array
    {
        $needsOpponent = ! in_array($this->matchType, ['bye', 'forfeit_general_us']);

        return [
            'matchType' => ['required', Rule::in(['normal', 'bye', 'forfeit_opponent', 'forfeit_general_opponent', 'forfeit_us', 'forfeit_general_us'])],
            'matchDate' => 'nullable|date',
            'isHome' => 'required|boolean',
            'opponentName' => $needsOpponent ? 'required|string|max:100' : 'nullable|string|max:100',
            'score' => $this->matchType === 'normal' ? ['nullable', 'regex:/^\d{1,2}-\d{1,2}$/'] : 'nullable|string',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $isBye = $this->matchType === 'bye';
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
            'score' => $this->matchType === 'normal' ? ($this->score ?: null) : null,
            'result' => $result?->value,
            'is_bye' => $isBye,
        ];

        if ($this->editingMatchResultId) {
            MatchResult::findOrFail($this->editingMatchResultId)->update($data);
            $this->success(__('Match updated'));
        } else {
            MatchResult::create($data);
            $this->success(__('Match added'));
        }

        $this->editModal = false;
    }

    public function updateFinalPosition(int $teamId, ?string $position): void
    {
        Team::findOrFail($teamId)->update(['final_position' => $position ?: null]);
    }

    public function with(): array
    {
        $categoryOrder = [
            LeagueCategory::MEN->name => 0,
            LeagueCategory::WOMEN->name => 1,
            LeagueCategory::VETERANS->name => 2,
        ];

        $teams = Team::with([
            'league',
            'matchResults' => fn ($q) => $q->where('season_id', $this->seasonId)->orderBy('match_date'),
        ])
            ->inClub()
            ->where('season_id', $this->seasonId)
            ->get()
            ->sortBy(fn (Team $t) => $categoryOrder[$t->league?->category] ?? 99);

        $stats = $teams->mapWithKeys(fn (Team $team) => [
            $team->id => $this->computeStats($team->matchResults),
        ]);

        $teamsByCategory = $teams->groupBy(fn (Team $t) => $t->league?->category ?? LeagueCategory::MEN->name);

        return [
            'seasons' => Season::orderByDesc('start_at')->get(),
            'teamsByCategory' => $teamsByCategory,
            'stats' => $stats,
            'matchTypeOptions' => [
                ['value' => 'normal',                   'label' => __('Match joué')],
                ['value' => 'bye',                      'label' => __('Bye (semaine exempt)')],
                ['value' => 'forfeit_opponent',         'label' => __('Forfait adverse')],
                ['value' => 'forfeit_general_opponent', 'label' => __('Forfait général adverse')],
                ['value' => 'forfeit_us',               'label' => __('Notre forfait')],
                ['value' => 'forfeit_general_us',       'label' => __('Notre forfait général')],
            ],
            'breadcrumbs' => Breadcrumb::make()->home()->add('Interclubs', '#')->results()->toArray(),
        ];
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
        if (! $this->score || ! str_contains($this->score, '-')) {
            return null;
        }

        [$left, $right] = array_map('intval', explode('-', $this->score, 2));
        [$ours, $theirs] = $this->isHome ? [$left, $right] : [$right, $left];

        if ($ours > $theirs) {
            return InterclubResult::WIN;
        }

        if ($ours < $theirs) {
            return InterclubResult::LOSS;
        }

        return InterclubResult::DRAW;
    }
};
