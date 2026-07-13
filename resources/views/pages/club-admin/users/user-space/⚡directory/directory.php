<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, WithPagination;

    #[Url]
    public string $rankingFilter = '';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $seasonFilter = null;

    #[Url]
    public ?int $teamFilter = null;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
        // Default the team-season filter to the current season.
        $this->seasonFilter ??= Season::current()?->id;
    }

    /**
     * Active members of the current season (competitive + recreational), ordered
     * by name. Contact fields are filtered per member in the view via
     * {@see User::contactVisibleTo()} — never queried out here.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function members(): LengthAwarePaginator
    {
        return User::active()
            ->when($this->search, fn (EloquentBuilder $q) => $q->searchName($this->search))
            ->when($this->rankingFilter, fn (EloquentBuilder $q) => $q->where('ranking', $this->rankingFilter))
            ->when($this->teamFilter, fn (EloquentBuilder $q) => $q->whereHas(
                'teams',
                fn (EloquentBuilder $t) => $t->where('teams.id', $this->teamFilter)
            ))
            ->with(['teams:id,name,league_id', 'teams.league:id,category'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(24);
    }

    /**
     * Teams of the selected season, for the team filter. Each option carries the
     * category ("A · Veterans") because team names alone (A, B, …) repeat across
     * men/women/veterans.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    #[Computed]
    public function teamsForFilter(): Collection
    {
        return Team::query()
            ->when($this->seasonFilter, fn (EloquentBuilder $q) => $q->where('season_id', $this->seasonFilter))
            ->with('league:id,category')
            ->orderBy('name')
            ->get(['id', 'name', 'league_id'])
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => ($category = LeagueCategory::fromName($team->league?->category))
                    ? $team->name.' · '.$category->label()
                    : $team->name,
            ]);
    }

    /**
     * Seasons for the season filter, oldest first.
     *
     * @return Collection<int, Season>
     */
    #[Computed]
    public function seasonsForFilter(): Collection
    {
        return Season::orderBy('start_at')->get(['id', 'name']);
    }

    /**
     * Distinct rankings currently held by active members, for the ranking filter.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function rankingsForFilter(): array
    {
        return User::active()
            ->whereNotNull('ranking')
            ->distinct()
            ->orderBy('ranking')
            ->pluck('ranking')
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        $currentSeasonId = Season::current()?->id;

        return array_values(array_filter([
            $this->rankingFilter !== '' ? ['key' => 'rankingFilter', 'label' => $this->rankingFilter] : null,
            $this->teamFilter ? [
                'key' => 'teamFilter',
                'label' => $this->teamsForFilter->firstWhere('id', $this->teamFilter)['name'] ?? (string) $this->teamFilter,
            ] : null,
            // Only chip the season when it departs from the current-season default.
            ($this->seasonFilter && $this->seasonFilter !== $currentSeasonId) ? [
                'key' => 'seasonFilter',
                'label' => Season::find($this->seasonFilter)?->name ?? (string) $this->seasonFilter,
            ] : null,
        ]));
    }

    public function clearFilters(): void
    {
        $this->reset(['rankingFilter', 'teamFilter']);
        $this->seasonFilter = Season::current()?->id;
        $this->resetPage();
    }

    /**
     * Removing the season chip returns to the current season (its default),
     * not to "all seasons"; other filters reset normally.
     */
    public function removeFilter(string $key): void
    {
        if ($key === 'seasonFilter') {
            $this->seasonFilter = Season::current()?->id;
            $this->teamFilter = null;
            $this->resetPage();

            return;
        }

        $this->reset([$key]);
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        // Changing the season invalidates the picked team (teams are per-season).
        if ($property === 'seasonFilter') {
            $this->teamFilter = null;
        }

        if (in_array($property, ['search', 'rankingFilter', 'teamFilter', 'seasonFilter'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Member directory'));
    }
};
