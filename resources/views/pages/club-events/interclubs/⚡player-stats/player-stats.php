<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\PlayerStats;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\PlayerStats;
use App\Domains\Competitions\Interclub\Services\PlayerStatsFilter;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\Ranking;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| Statistiques des joueurs
|--------------------------------------------------------------------------
|
| L'écran du comité pour décider des sélections, des noyaux et des
| compétitions où inscrire les jeunes (grilling du 2026-10-25). Il se lit, il
| n'écrit rien.
|
| Six classements sur la même population : les meilleurs et les moins bons au
| score par match — victoires au-delà de ce que le classement promettait —, ceux
| qui jouent le plus et le moins, ceux qui progressent et ceux qui reculent.
| Cocher deux à quatre joueurs les compare côte à côte.
|
*/
new class extends Component
{
    use HasBreadcrumbs, HasBulkActions, HasFilterDrawer;

    /** Comparer plus de quatre joueurs ne tient plus en colonnes. */
    public const int MAX_COMPARED = 4;

    #[Url(as: 'born_from', except: '')]
    public string $bornFrom = '';

    #[Url(as: 'born_to', except: '')]
    public string $bornTo = '';

    #[Url(except: '')]
    public string $gender = '';

    #[Url(as: 'former', except: false)]
    public bool $includeFormer = false;

    /** Interclub, tournois, ou les deux additionnés. */
    #[Url(except: 'interclub')]
    public string $kind = 'interclub';

    /** Sous ce nombre de matchs, un joueur n'entre pas dans les classements de performance. */
    #[Url(as: 'min', except: 5)]
    public int $minMatches = 5;

    #[Url(as: 'team_only', except: false)]
    public bool $onlyTeamMatches = false;

    /**
     * La période : `current` (la saison en cours), `all`, `months-N`, ou l'id
     * d'une saison.
     */
    #[Url(except: 'current')]
    public string $period = 'current';

    /** @var array<int, string> */
    #[Url(except: [])]
    public array $rankings = [];

    #[Url(except: 'best')]
    public string $tab = 'best';

    #[Url(as: 'team', except: '')]
    public string $teamId = '';

    /** Le pivot des onglets de tendance : la saison contre la précédente, ou la forme récente. */
    #[Url(as: 'trend', except: 'season')]
    public string $trendBasis = 'season';

    public function clearFilters(): void
    {
        $this->reset(['bornFrom', 'bornTo', 'gender', 'includeFormer', 'onlyTeamMatches', 'rankings', 'teamId']);
    }

    /**
     * Ouvrir la comparaison des joueurs cochés.
     */
    public function compare(): void
    {
        $ids = array_slice(array_map('intval', $this->selected), 0, self::MAX_COMPARED);

        $this->redirectRoute('admin.interclubs.player-stats.compare', [
            'ids' => implode(',', $ids),
            'kind' => $this->kind,
            'period' => $this->period,
        ], navigate: true);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        $chips = [];

        foreach ($this->rankings as $ranking) {
            $chips[] = ['key' => 'rankings_' . $ranking, 'label' => $ranking];
        }

        if ($this->gender !== '') {
            $chips[] = ['key' => 'gender', 'label' => Gender::tryFrom($this->gender)?->getLabel() ?? $this->gender];
        }

        if ($this->bornFrom !== '' || $this->bornTo !== '') {
            $chips[] = ['key' => 'born', 'label' => __('Born :from – :to', ['from' => $this->bornFrom ?: '…', 'to' => $this->bornTo ?: '…'])];
        }

        if ($this->teamId !== '') {
            $chips[] = ['key' => 'teamId', 'label' => Team::with('club')->find((int) $this->teamId)?->fullName() ?? '—'];
        }

        if ($this->onlyTeamMatches) {
            $chips[] = ['key' => 'onlyTeamMatches', 'label' => __('Matches with this team only')];
        }

        if ($this->includeFormer) {
            $chips[] = ['key' => 'includeFormer', 'label' => __('Former members included')];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->rows->count();
    }

    public function removeFilter(string $key): void
    {
        if (str_starts_with($key, 'rankings_')) {
            $this->rankings = array_values(array_diff($this->rankings, [substr($key, strlen('rankings_'))]));

            return;
        }

        if ($key === 'born') {
            $this->reset(['bornFrom', 'bornTo']);

            return;
        }

        $this->reset([$key]);
    }

    public function render(): View
    {
        return $this->view()->title(__('Player statistics'));
    }

    /**
     * La population avec tous ses chiffres, avant tri et seuil.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): Collection
    {
        return app(PlayerStats::class)->rows($this->filter());
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
            'genders' => Gender::options(),
            'kinds' => [
                'interclub' => __('Interclub'),
                'tournaments' => __('Tournaments'),
                'both' => __('Both'),
            ],
            'periods' => $this->periodOptions(),
            'rankingOptions' => Ranking::options(includeNA: false),
            'rows' => $this->ranked(),
            'tabs' => [
                'best' => __('Best'),
                'worst' => __('Weakest'),
                'most' => __('Play the most'),
                'least' => __('Play the least'),
                'rising' => __('Rising'),
                'falling' => __('Falling'),
            ],
            'teams' => Team::with(['club', 'season'])
                ->inClub()
                ->orderByDesc('season_id')
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team): array => ['id' => (string) $team->id, 'name' => $team->fullName() . ' · ' . $team->season?->name])
                ->all(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Interclubs'), route('admin.interclubs.captain-selection'))
            ->current(__('Player statistics'));
    }

    /**
     * @return array<int, string>
     */
    protected function getPageIds(): array
    {
        return $this->ranked()->pluck('user_id')->map(fn (int $id): string => (string) $id)->all();
    }

    /**
     * @return Builder<User>
     */
    protected function matchingQuery(): Builder
    {
        return app(PlayerStats::class)->population($this->filter());
    }

    /**
     * @return array<int, string>
     */
    protected function selectionScope(): array
    {
        return ['bornFrom', 'bornTo', 'gender', 'includeFormer', 'rankings', 'teamId'];
    }

    private function filter(): PlayerStatsFilter
    {
        return PlayerStatsFilter::forPeriod($this->period, [
            'rankings' => $this->rankings,
            'gender' => $this->gender,
            'bornFrom' => $this->bornFrom,
            'bornTo' => $this->bornTo,
            'teamId' => $this->teamId,
            'onlyTeamMatches' => $this->onlyTeamMatches,
            'includeFormer' => $this->includeFormer,
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function periodOptions(): array
    {
        return [
            ['id' => 'current', 'name' => __('This season')],
            ['id' => 'months-3', 'name' => __('Last 3 months')],
            ['id' => 'months-12', 'name' => __('Last 12 months')],
            ['id' => 'all', 'name' => __('All seasons')],
            ...Season::query()->orderByDesc('start_at')->get()
                ->map(fn (Season $season): array => ['id' => (string) $season->id, 'name' => $season->name])
                ->all(),
        ];
    }

    /**
     * The rows of the current tab, in its order.
     *
     * The performance tabs leave out whoever has not played the threshold: a
     * single win would otherwise crown the club's best player. The volume tabs
     * keep everybody — the competitor at zero is the line they exist for.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function ranked(): Collection
    {
        $kind = in_array($this->kind, PlayerStats::KINDS, true) ? $this->kind : 'interclub';
        $figures = fn (array $row): array => $row[$kind];
        $volume = fn (array $row): int => match ($kind) {
            'interclub' => $row['interclub']['ties'],
            'tournaments' => $row['tournaments']['tournaments'],
            default => $row['both']['played'],
        };
        $trend = fn (array $row): ?float => $figures($row)['trend'][$this->trendBasis === 'form' ? 'form' : 'season'];
        $enough = fn (array $row): bool => $figures($row)['played'] >= max(1, $this->minMatches) && $figures($row)['score_per_match'] !== null;

        $ranked = match ($this->tab) {
            'worst' => $this->rows->filter($enough)->sortBy(fn (array $row): float => $figures($row)['score_per_match']),
            'most' => $this->rows->sortByDesc($volume),
            'least' => $this->rows->sortBy($volume),
            'rising' => $this->rows->filter(fn (array $row): bool => $trend($row) !== null)->sortByDesc($trend),
            'falling' => $this->rows->filter(fn (array $row): bool => $trend($row) !== null)->sortBy($trend),
            default => $this->rows->filter($enough)->sortByDesc(fn (array $row): float => $figures($row)['score_per_match']),
        };

        return $ranked->values();
    }
};
