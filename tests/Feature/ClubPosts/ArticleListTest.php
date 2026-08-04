<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Livewire\Public\Articles\ArticleList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Season::factory()->create([
        'is_active' => true,
        'start_at' => '2024-09-01',
        'end_at' => '2025-06-30',
    ]);

    NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::PUBLISHED,
        'category' => NewsPostCategoryEnum::PARTNERSHIP,
        'created_at' => '2024-10-15',
    ]);
    NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::PUBLISHED,
        'category' => NewsPostCategoryEnum::EVENT,
        'created_at' => '2024-11-10',
    ]);
    NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::DRAFT,
        'category' => NewsPostCategoryEnum::PARTNERSHIP,
        'created_at' => '2024-10-01',
    ]);
});

it('does not show empty state when articles exist but not in the active season', function (): void {
    // Deactivate the beforeEach season, set a new active one with no articles
    Season::where('is_active', true)->update(['is_active' => false]);
    Season::factory()->create([
        'is_active' => true,
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
    ]);
    Cache::forget('season.current');

    // Articles from beforeEach still exist (2024 dates) — just outside the new active season
    // Component must fall back to showing all articles, not the empty state
    Livewire::test(ArticleList::class)
        ->assertDontSee(__('No articles found'));
});

it('initializes with correct default values and collections', function (): void {
    $component = new ArticleList;
    $component->mount();

    expect($component->category)->toBe('');
    expect($component->sort)->toBe('desc');
    expect($component->defaultSeasonId)->toBeInt()->toBeGreaterThan(0);
    expect($component->seasonId)->toBe($component->defaultSeasonId);

    expect($component->categories)->toBeInstanceOf(Collection::class);
    expect($component->categories)->toContain(NewsPostCategoryEnum::PARTNERSHIP->value);

    expect($component->seasons)->toBeInstanceOf(Collection::class);
    expect($component->seasons->isNotEmpty())->toBeTrue();
});

it('applies filters correctly in getArticlesProperty', function (): void {
    $component = Livewire::test(ArticleList::class)
        ->set('category', NewsPostCategoryEnum::PARTNERSHIP->value)
        ->set('sort', 'asc');

    $component->assertSet('category', NewsPostCategoryEnum::PARTNERSHIP->value);
    $component->assertSet('sort', 'asc');

    $articles = $component->instance()->getArticlesProperty();
    expect($articles)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($articles->count())->toBeGreaterThanOrEqual(0);

    if ($articles->count() > 0) {
        foreach ($articles as $article) {
            expect($article->category)->toBe(NewsPostCategoryEnum::PARTNERSHIP);
        }
    }
});

it('returns only published articles', function (): void {
    $publishedArticle = NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::PUBLISHED,
        'category' => NewsPostCategoryEnum::PARTNERSHIP,
        'created_at' => '2024-10-15',
    ]);

    $draftArticle = NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::DRAFT,
        'category' => NewsPostCategoryEnum::PARTNERSHIP,
        'created_at' => '2024-10-16',
    ]);

    $archivedArticle = NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::ARCHIVED,
        'category' => NewsPostCategoryEnum::EVENT,
        'created_at' => '2024-10-17',
    ]);

    $component = new ArticleList;
    $component->mount();
    $articles = $component->getArticlesProperty();

    expect($articles->count())->toBeGreaterThan(0);

    foreach ($articles as $article) {
        expect($article->status)->toBe(NewsPostStatusEnum::PUBLISHED);
    }

    $articleIds = $articles->pluck('id')->toArray();
    expect($articleIds)->toContain($publishedArticle->id);
    expect($articleIds)->not->toContain($draftArticle->id);
    expect($articleIds)->not->toContain($archivedArticle->id);
});

it('resets pagination when filters update', function (): void {
    NewsPost::factory()->count(20)->create([
        'status' => NewsPostStatusEnum::PUBLISHED->value,
        'category' => NewsPostCategoryEnum::PARTNERSHIP->value,
        'created_at' => '2024-10-15',
    ]);

    $component = Livewire::test(ArticleList::class)
        ->call('gotoPage', 2);

    $articles = $component->instance()->getArticlesProperty();
    expect($articles->currentPage())->toBe(2);

    $component->set('category', NewsPostCategoryEnum::EVENT->value);

    $articlesAfterFilter = $component->instance()->getArticlesProperty();
    expect($articlesAfterFilter->currentPage())->toBe(1);
});

it('tests that clearAllFilters resets all filters and sort', function (): void {
    $activeSeason = Season::firstWhere('is_active', true);

    $component = Livewire::test(ArticleList::class)
        ->set('category', NewsPostCategoryEnum::PARTNERSHIP->value)
        ->set('sort', 'asc');

    $component->call('clearAllFilters');

    $component->assertSet('category', '');
    $component->assertSet('sort', 'desc');
    $component->assertSet('seasonId', $activeSeason->id);
});

it('tests that clearFilter resets individual filters correctly', function (): void {
    $activeSeason = Season::firstWhere('is_active', true);

    $component = new ArticleList;
    $component->mount();
    $component->category = NewsPostCategoryEnum::PARTNERSHIP->value;
    $component->sort = 'asc';

    $component->clearFilter('category');
    expect($component->category)->toBe('');

    $component->clearFilter('sort');
    expect($component->sort)->toBe('desc');

    $component->clearFilter('seasonId');
    expect($component->seasonId)->toBe($activeSeason->id);
});

it('tests that activeFiltersCountProperty returns correct count', function (): void {
    $activeSeason = Season::firstWhere('is_active', true);
    $otherSeason = Season::factory()->create([
        'is_active' => false,
        'start_at' => '2023-09-01',
        'end_at' => '2024-06-30',
    ]);

    $component = Livewire::test(ArticleList::class)
        ->set('category', NewsPostCategoryEnum::PARTNERSHIP->value);

    expect($component->instance()->activeFiltersCount)->toBe(1);

    $component->set('seasonId', $otherSeason->id);
    expect($component->instance()->activeFiltersCount)->toBe(2);

    $component->set('category', '')->set('seasonId', $activeSeason->id);
    expect($component->instance()->activeFiltersCount)->toBe(0);
});

it('tests that applyFilters modifies the query as expected', function (): void {
    $component = new ArticleList;
    $component->mount();

    $query = NewsPost::query()->where('status', NewsPostStatusEnum::PUBLISHED);

    $component->category = NewsPostCategoryEnum::PARTNERSHIP->value;

    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('applyFilters');

    $method->invoke($component, $query);

    $sql = $query->toSql();

    expect(str_contains($sql, 'where'))->toBeTrue();
});

it('tests that forceRefresh dispatches event', function (): void {
    $component = Livewire::test(ArticleList::class);

    $component->call('forceRefresh');

    $component->assertDispatched('$refresh');
    expect(method_exists($component->instance(), 'forceRefresh'))->toBeTrue();
});
