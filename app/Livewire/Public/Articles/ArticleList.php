<?php

declare(strict_types=1);

namespace App\Livewire\Public\Articles;

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ArticleList extends Component
{
    use WithPagination;

    public Collection $categories;

    public string $category = '';

    public int $defaultSeasonId = 0;

    public int $seasonId = 0;

    public Collection $seasons;

    public string $sort = 'desc';

    protected array $queryString = [
        'category' => ['except' => ''],
        'seasonId' => ['except' => 0, 'as' => 'season'],
        'sort' => ['except' => 'desc'],
    ];

    public function clearAllFilters(): void
    {
        $this->category = '';
        $this->sort = 'desc';
        $this->seasonId = $this->defaultSeasonId;
    }

    public function clearFilter(string $filter): void
    {
        match ($filter) {
            'category' => $this->category = '',
            'sort' => $this->sort = 'desc',
            'seasonId' => $this->seasonId = $this->defaultSeasonId,
            default => null,
        };
    }

    public function forceRefresh(): void
    {
        $this->dispatch('$refresh');
    }

    public function getActiveFiltersCountProperty(): int
    {
        return collect([
            $this->category !== '',
            $this->seasonId !== $this->defaultSeasonId,
        ])->filter()->count();
    }

    public function getArticlesProperty(): LengthAwarePaginator
    {
        $query = NewsPost::query()
            ->where('status', NewsPostStatusEnum::PUBLISHED);

        $this->applyFilters($query);

        return $query->orderBy('created_at', $this->sort)->paginate(9);
    }

    public function mount(): void
    {
        $this->categories = collect(NewsPostCategoryEnum::cases())->pluck('value');
        $this->seasons = $this->loadSeasons();

        $currentSeason = Season::current();
        $this->defaultSeasonId = $this->resolveDefaultSeasonId($currentSeason);

        if ($this->seasonId === 0) {
            $this->seasonId = $this->defaultSeasonId;
        }
    }

    public function render(): View
    {
        return view('livewire.public.articles.articles-list', [
            'articles' => $this->articles,
            'activeFiltersCount' => $this->activeFiltersCount,
        ]);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['category', 'seasonId', 'sort'], true)) {
            $this->resetPage();
        }
    }

    private function applyFilters(Builder $query): void
    {
        if ($this->category) {
            $query->where('category', $this->category);
        }

        if ($this->seasonId > 0) {
            $season = $this->seasons->firstWhere('id', $this->seasonId);
            if ($season) {
                $query->whereBetween('created_at', [$season->start_at->startOfDay(), $season->end_at->endOfDay()]);
            }
        }
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

    private function resolveDefaultSeasonId(?Season $season): int
    {
        if (! $season) {
            return 0;
        }

        $hasArticles = NewsPost::where('status', NewsPostStatusEnum::PUBLISHED)
            ->whereBetween('created_at', [$season->start_at->startOfDay(), $season->end_at->endOfDay()])
            ->exists();

        return $hasArticles ? $season->id : 0;
    }
}
