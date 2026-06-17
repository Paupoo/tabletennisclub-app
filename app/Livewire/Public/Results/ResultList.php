<?php

declare(strict_types=1);

namespace App\Livewire\Public\Results;

use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\LeagueCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class ResultList extends Component
{
    public string $category = '';

    public int $defaultSeasonId = 0;

    public string $division = '';

    public int $seasonId = 0;

    public Collection $seasons;

    public int $teamId = 0;

    protected array $queryString = [
        'seasonId' => ['except' => 0, 'as' => 'season'],
        'category' => ['except' => ''],
        'division' => ['except' => ''],
        'teamId' => ['except' => 0, 'as' => 'team'],
    ];

    public function clearAllFilters(): void
    {
        $this->category = '';
        $this->division = '';
        $this->teamId = 0;
        $this->seasonId = $this->defaultSeasonId;
    }

    public function clearFilter(string $filter): void
    {
        match ($filter) {
            'category' => $this->category = '',
            'division' => $this->division = '',
            'teamId' => $this->teamId = 0,
            'seasonId' => $this->seasonId = $this->defaultSeasonId,
            default => null,
        };
    }

    public function getActiveFiltersCountProperty(): int
    {
        return collect([
            $this->seasonId !== $this->defaultSeasonId,
            $this->category !== '',
            $this->division !== '',
            $this->teamId !== 0,
        ])->filter()->count();
    }

    public function getAvailableCategoriesProperty(): Collection
    {
        return $this->allTeamsForSeason()
            ->pluck('league.category')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $dbValue) => [
                'value' => $dbValue,
                'label' => $this->categoryLabel($dbValue),
            ]);
    }

    public function getAvailableDivisionsProperty(): Collection
    {
        return $this->allTeamsForSeason()
            ->when($this->category, fn (Collection $c) => $c->filter(
                fn (Team $t) => $t->league?->category === $this->category
            ))
            ->pluck('league.division')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    public function getAvailableTeamsProperty(): Collection
    {
        return $this->allTeamsForSeason()
            ->when($this->category, fn (Collection $c) => $c->filter(
                fn (Team $t) => $t->league?->category === $this->category
            ))
            ->when($this->division, fn (Collection $c) => $c->filter(
                fn (Team $t) => $t->league?->division === $this->division
            ))
            ->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name])
            ->values();
    }

    public function getTeamsByCategoryProperty(): array
    {
        $season = $this->currentSeason();

        if (! $season) {
            return [];
        }

        $categoryOrder = [
            LeagueCategory::MEN->name => 0,
            LeagueCategory::WOMEN->name => 1,
            LeagueCategory::VETERANS->name => 2,
        ];

        $categoryLabels = [
            LeagueCategory::MEN->name => 'Hommes',
            LeagueCategory::WOMEN->name => 'Dames',
            LeagueCategory::VETERANS->name => 'Vétérans',
        ];

        $grouped = Team::with([
            'league',
            'interclubResults' => fn (Relation $q) => $q->where('season_id', $season->id)->orderBy('match_date'),
        ])
            ->inClub()
            ->where('season_id', $season->id)
            ->when($this->category, fn (Builder $q) => $q->whereHas(
                'league', fn (Builder $lq) => $lq->where('category', $this->category)
            ))
            ->when($this->division, fn (Builder $q) => $q->whereHas(
                'league', fn (Builder $lq) => $lq->where('division', $this->division)
            ))
            ->when($this->teamId > 0, fn (Builder $q) => $q->where('id', $this->teamId))
            ->get()
            ->sortBy(fn (Team $t) => $categoryOrder[$t->league?->category] ?? 99)
            ->groupBy(fn (Team $t) => $t->league?->category ?? LeagueCategory::MEN->name);

        $result = [];
        foreach ($grouped as $catName => $teams) {
            $result[] = [
                'category' => $catName,
                'label' => $categoryLabels[$catName] ?? $catName,
                'teams' => $teams->map(fn (Team $team) => [
                    'name' => 'Équipe ' . $team->name . ($team->league ? ' - Division ' . $team->league->division : ''),
                    'position' => $team->final_position ?? '—',
                    'position_class' => $this->positionClass($team->final_position),
                    'matches' => $team->interclubResults->map(fn (InterclubResult $mr) => [
                        'date' => $mr->is_bye ? 'Bye' : $mr->match_date?->format('d M Y'),
                        'opponent' => $mr->opponent_name ?? 'Bye',
                        'venue' => $mr->is_home ? 'Domicile' : 'Extérieur',
                        'score' => $mr->score ?? ($mr->is_bye ? 'Bye' : '—'),
                        'result' => $this->frenchResult($mr),
                    ])->toArray(),
                    'stats' => $this->buildStats($team->interclubResults),
                ])->toArray(),
            ];
        }

        return $result;
    }

    public function mount(): void
    {
        $this->seasons = $this->loadSeasons();
        $this->defaultSeasonId = Season::current()?->id ?? 0;

        if ($this->seasonId === 0) {
            $this->seasonId = $this->defaultSeasonId;
        }
    }

    public function render(): View
    {
        return view('livewire.public.results.result-list', [
            'teamsByCategory' => $this->teamsByCategory,
            'activeFiltersCount' => $this->activeFiltersCount,
            'availableCategories' => $this->availableCategories,
            'availableDivisions' => $this->availableDivisions,
            'availableTeams' => $this->availableTeams,
        ]);
    }

    public function updatedCategory(): void
    {
        $this->division = '';
        $this->teamId = 0;
    }

    public function updatedDivision(): void
    {
        $this->teamId = 0;
    }

    public function updatedSeasonId(): void
    {
        $this->category = '';
        $this->division = '';
        $this->teamId = 0;
    }

    private function allTeamsForSeason(): Collection
    {
        $season = $this->currentSeason();

        if (! $season) {
            return collect();
        }

        return Team::with('league')
            ->inClub()
            ->where('season_id', $season->id)
            ->get();
    }

    /** @return array{played: int, wins: int, losses: int, win_rate: int} */
    private function buildStats(Collection $interclubResults): array
    {
        $real = $interclubResults->where('is_bye', false)->filter(fn (InterclubResult $mr) => $mr->result !== null);
        $played = $real->count();
        $wins = $real->filter(fn (InterclubResult $mr) => in_array($mr->result, [InterclubResultEnum::WIN, InterclubResultEnum::FORFEIT_WIN]))->count();
        $losses = $real->filter(fn (InterclubResult $mr) => in_array($mr->result, [InterclubResultEnum::LOSS, InterclubResultEnum::FORFEIT_LOSS]))->count();

        return [
            'played' => $played,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $played > 0 ? (int) round($wins / $played * 100) : 0,
        ];
    }

    private function categoryLabel(string $dbCategory): string
    {
        $case = collect(LeagueCategory::cases())->firstWhere('name', $dbCategory);

        return $case ? __($case->value) : $dbCategory;
    }

    private function currentSeason(): ?Season
    {
        return $this->seasons->firstWhere('id', $this->seasonId);
    }

    private function frenchResult(InterclubResult $mr): string
    {
        if ($mr->is_bye) {
            return 'Bye';
        }

        if ($mr->result === null) {
            return '—';
        }

        return match ($mr->result) {
            InterclubResultEnum::WIN => 'Victoire',
            InterclubResultEnum::LOSS => 'Défaite',
            InterclubResultEnum::DRAW => 'Nul',
            InterclubResultEnum::FORFEIT_WIN => 'Forfait Adverse',
            InterclubResultEnum::FORFEIT_LOSS => 'Forfait',
            InterclubResultEnum::WITHDRAWAL => 'Forfait Général',
            InterclubResultEnum::WITHDRAWAL_OPPONENT => 'Forfait Général Adverse',
        };
    }

    private function loadSeasons(): Collection
    {
        $currentSeason = Season::current();

        return Season::orderByDesc('start_at')
            ->when(
                $currentSeason,
                fn (Builder $q) => $q->where('start_at', '<', $currentSeason->start_at),
                fn (Builder $q) => $q->where('start_at', '<', now())
            )
            ->limit(5)
            ->get()
            ->when($currentSeason, fn (Collection $coll) => $coll->prepend($currentSeason));
    }

    private function positionClass(?string $position): string
    {
        if (! $position) {
            return 'bg-gray-100 text-gray-800';
        }

        if (str_contains($position, '1')) {
            return 'bg-yellow-100 text-yellow-800';
        }

        if (str_contains($position, '2') || str_contains($position, '3')) {
            return 'bg-orange-100 text-orange-800';
        }

        return 'bg-gray-100 text-gray-800';
    }
}
