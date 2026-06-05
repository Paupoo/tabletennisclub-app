<?php

declare(strict_types=1);

namespace App\Livewire\Public\Articles;

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ArticleList extends Component
{
    use WithPagination;

    public Collection $categories;

    public string $category = '';

    public string $month = '';

    public Collection $months;

    public string $sort = 'desc';

    public string $year = '';

    public Collection $years;

    protected array $queryString = [
        'category' => ['except' => ''],
        'year' => ['except' => ''],
        'month' => ['except' => ''],
        'sort' => ['except' => 'desc'],
    ];

    public function clearAllFilters(): void
    {
        $this->reset(['category', 'year', 'month', 'sort']);
        $this->sort = 'desc';
    }

    public function clearFilter(string $filter): void
    {
        if (property_exists($this, $filter)) {
            $this->{$filter} = $filter === 'sort' ? 'desc' : '';
        }
    }

    public function forceRefresh(): void
    {
        $this->dispatch('$refresh');
    }

    public function getActiveFiltersCountProperty(): int
    {
        return collect([$this->category, $this->year, $this->month])
            ->filter()
            ->count();
    }

    public function getArticlesProperty(): LengthAwarePaginator
    {
        $query = NewsPost::query()
            ->where('status', NewsPostStatusEnum::PUBLISHED->value);

        $this->applyFilters($query);

        return $query->orderBy('created_at', $this->sort)
            ->paginate(9);
    }

    public function mount(): void
    {
        $this->categories = collect(NewsPostCategoryEnum::cases());
        $this->years = $this->loadYears();
        $this->months = $this->loadMonths();
        $this->categories = collect(NewsPostCategoryEnum::cases())
            ->pluck('value');
    }

    public function render(): View
    {
        return view('livewire.public.articles.articles-list', [
            'articles' => $this->articles,
            'clubPosts' => $this->articles,
            'categories' => $this->categories,
            'years' => $this->years,
            'months' => $this->months,
            'activeFiltersCount' => $this->activeFiltersCount,
        ]);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['category', 'year', 'month', 'sort'], true)) {
            $this->resetPage();
        }
    }

    private function applyFilters(Builder $query): void
    {
        if ($this->category) {
            $query->where('category', $this->category);
        }

        if ($this->year) {
            $query->whereYear('created_at', $this->year);
        }

        if ($this->month) {
            $query->whereMonth('created_at', $this->month);
        }
    }

    private function loadMonths(): Collection
    {
        return collect([
            '01' => 'Janvier',
            '02' => 'Février',
            '03' => 'Mars',
            '04' => 'Avril',
            '05' => 'Mai',
            '06' => 'Juin',
            '07' => 'Juillet',
            '08' => 'Août',
            '09' => 'Septembre',
            '10' => 'Octobre',
            '11' => 'Novembre',
            '12' => 'Décembre',
        ]);
    }

    private function loadYears(): Collection
    {

        return NewsPost::query()
            ->whereNotNull('created_at')
            ->get(['created_at'])
            ->pluck('created_at')
            ->map(fn (Carbon $date) => $date->year)
            ->unique()
            ->sortDesc()
            ->values();
    }
}
