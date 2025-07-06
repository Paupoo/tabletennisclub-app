<?php
declare(strict_types=1);

namespace App\Livewire\Public\Articles;

use App\Enums\ArticlesCategoryEnum;
use App\Enums\ArticlesStatusEnum;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Collection;

class ArticleList extends Component
{
    use WithPagination;

    public string $category = '';
    public Collection $categories;
    public string $year = '';
    public Collection $years;
    public string $month = '';
    public Collection $months;
    public string $sort = 'desc';


    protected $queryString = [
        'category' => ['except' => ''],
        'year'     => ['except' => ''],
        'month'    => ['except' => ''],
        'sort'     => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        $this->categories = collect(ArticlesCategoryEnum::cases());
        $this->years = $this->loadYears();
        $this->months = $this->loadMonths();
        $this->categories = collect(ArticlesCategoryEnum::cases())
            ->pluck('value');
    }

    public function updated($name, $value): void
    {
        \Log::info("=== UPDATED ===");
        \Log::info("Property: $name");
        \Log::info("New value: " . ($value ?: 'empty'));
        \Log::info("Category: " . ($this->category ?: 'empty'));
        \Log::info("Year: " . ($this->year ?: 'empty'));
        \Log::info("Month: " . ($this->month ?: 'empty'));
        
        // Test direct de la requête
        $testQuery = Article::query()->where('status', 'published');
        $this->applyFilters($testQuery);
        \Log::info("SQL Query: " . $testQuery->toSql());
        \Log::info("Bindings: " . json_encode($testQuery->getBindings()));
        \Log::info("Count: " . $testQuery->count());
    }

    public function updating($name): void
    {
        if (in_array($name, ['category', 'year', 'month', 'sort'], true)) {
            $this->resetPage();
        }
    }

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

    public function getArticlesProperty(): LengthAwarePaginator
    {
        $query = Article::query()
            ->where('status', ArticlesStatusEnum::PUBLISHED->value);

        $this->applyFilters($query);

        return $query->orderBy('created_at', $this->sort)
            ->paginate(9);
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


    public function getActiveFiltersCountProperty(): int
    {
        return collect([$this->category, $this->year, $this->month])
            ->filter()
            ->count();
    }

    public function render()
    {
        return view('livewire.public.articles.articles-list', [
            'articles' => $this->articles,
            'categories' => $this->categories,
            'years' => $this->years,
            'months' => $this->months,
            'activeFiltersCount' => $this->activeFiltersCount,
        ]);
    }

    public function forceRefresh(): void
{
    $this->dispatch('$refresh');
}
    private function loadYears(): Collection
    {
        return Article::query()
            ->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');
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
}
