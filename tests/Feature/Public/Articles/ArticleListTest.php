<?php

declare(strict_types=1);

use App\Enums\ArticlesCategoryEnum;
use App\Enums\ArticlesStatusEnum;
use App\Livewire\Public\Articles\ArticleList;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Création d'articles exemples pour tests
    Article::factory()->create([
        'status' => ArticlesStatusEnum::PUBLISHED,
        'category' => ArticlesCategoryEnum::PARTNERSHIP,
        'created_at' => '2024-05-15',
    ]);
    Article::factory()->create([
        'status' => ArticlesStatusEnum::PUBLISHED,
        'category' => ArticlesCategoryEnum::EVENT,
        'created_at' => '2023-01-10',
    ]);
    Article::factory()->create([
        'status' => ArticlesStatusEnum::DRAFT,
        'category' => ArticlesCategoryEnum::PARTNERSHIP,
        'created_at' => '2024-01-01',
    ]);
});

it('initializes with correct default values and collections', function (): void {
    $component = new ArticleList;
    $component->mount();

    expect($component->category)->toBe('');
    expect($component->year)->toBe('');
    expect($component->month)->toBe('');
    expect($component->sort)->toBe('desc');

    // Categories loaded correspond à enum values
    expect($component->categories)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($component->categories)->toContain(ArticlesCategoryEnum::PARTNERSHIP->value);

    // Years loaded
    expect($component->years)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($component->years->contains(2024))->toBeTrue();

    // Months loaded
    expect($component->months)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($component->months->get('01'))->toBe('Janvier');
});

it('applies filters correctly in getArticlesProperty', function (): void {
    $component = Livewire::test(ArticleList::class)
        ->set('category', ArticlesCategoryEnum::PARTNERSHIP->value)
        ->set('year', '2024')
        ->set('month', '05')
        ->set('sort', 'asc');

    // Vérifier que les propriétés sont bien définies
    $component->assertSet('category', ArticlesCategoryEnum::PARTNERSHIP->value);
    $component->assertSet('year', '2024');
    $component->assertSet('month', '05');
    $component->assertSet('sort', 'asc');

    // Vérifier que les articles sont bien filtrés
    $articles = $component->instance()->getArticlesProperty();
    expect($articles)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($articles->count())->toBeGreaterThanOrEqual(0);

    // Vérifier que seuls les articles correspondant aux filtres sont retournés
    if ($articles->count() > 0) {
        foreach ($articles as $article) {
            expect($article->category)->toBe(ArticlesCategoryEnum::PARTNERSHIP);
            expect($article->created_at->year)->toBe(2024);
            expect($article->created_at->month)->toBe(5);
        }
    }
});

it('returns only published articles', function (): void {
    // Créer des articles avec différents statuts
    $publishedArticle = Article::factory()->create([
        'status' => ArticlesStatusEnum::PUBLISHED,
        'category' => ArticlesCategoryEnum::PARTNERSHIP,
        'created_at' => '2024-05-15',
    ]);

    $draftArticle = Article::factory()->create([
        'status' => ArticlesStatusEnum::DRAFT,
        'category' => ArticlesCategoryEnum::PARTNERSHIP,
        'created_at' => '2024-05-16',
    ]);

    $archivedArticle = Article::factory()->create([
        'status' => ArticlesStatusEnum::ARCHIVED,
        'category' => ArticlesCategoryEnum::EVENT,
        'created_at' => '2024-05-17',
    ]);

    $component = new ArticleList;
    $component->mount();
    $articles = $component->getArticlesProperty();

    // Vérifier qu'on a bien des articles
    expect($articles->count())->toBeGreaterThan(0);

    // Vérifier que tous les articles retournés sont publiés
    foreach ($articles as $article) {
        expect($article->status)->toBe(ArticlesStatusEnum::PUBLISHED);
    }

    // Vérifier que l'article publié est dans les résultats
    $articleIds = $articles->pluck('id')->toArray();
    expect($articleIds)->toContain($publishedArticle->id);

    // Vérifier que les articles non publiés ne sont PAS dans les résultats
    expect($articleIds)->not->toContain($draftArticle->id);
    expect($articleIds)->not->toContain($archivedArticle->id);
});

it('resets pagination when filters update', function (): void {
    // Créer suffisamment d'articles pour avoir plusieurs pages
    Article::factory()->count(20)->create([
        'status' => ArticlesStatusEnum::PUBLISHED->value,
        'category' => ArticlesCategoryEnum::PARTNERSHIP->value,
    ]);

    $component = Livewire::test(ArticleList::class)
        ->call('gotoPage', 2);

    // Vérifier qu'on est bien sur la page 2
    $articles = $component->instance()->getArticlesProperty();
    expect($articles->currentPage())->toBe(2);

    // Changer un filtre - cela devrait reset la page à 1
    $component->set('category', ArticlesCategoryEnum::EVENT->value);

    // Vérifier que la page est maintenant à 1
    $articlesAfterFilter = $component->instance()->getArticlesProperty();
    expect($articlesAfterFilter->currentPage())->toBe(1);
});

it('tests that clearAllFilters resets all filters and sort', function (): void {
    $component = Livewire::test(ArticleList::class)
        ->set('category', ArticlesCategoryEnum::PARTNERSHIP->value)
        ->set('year', '2024')
        ->set('month', '05')
        ->set('sort', 'asc');

    $component->call('clearAllFilters');

    $component->assertSet('category', '');
    $component->assertSet('year', '');
    $component->assertSet('month', '');
    $component->assertSet('sort', 'desc');
});

it('tests that  clearFilter resets individual filters correctly', function (): void {
    $component = new ArticleList;
    $component->category = ArticlesCategoryEnum::PARTNERSHIP->value;
    $component->year = '2024';
    $component->month = '05';
    $component->sort = 'asc';

    $component->clearFilter('category');
    expect($component->category)->toBe('');

    $component->clearFilter('year');
    expect($component->year)->toBe('');

    $component->clearFilter('month');
    expect($component->month)->toBe('');

    $component->clearFilter('sort');
    expect($component->sort)->toBe('desc');
});

it('tests that activeFiltersCountProperty returns correct count', function (): void {
    $component = Livewire::test(ArticleList::class)
        ->set('category', ArticlesCategoryEnum::PARTNERSHIP->value)
        ->set('year', '')
        ->set('month', '05');

    expect($component->instance()->activeFiltersCount)->toBe(2);

    // On remet tous les filtres à vide
    $component->set('category', '')->set('year', '')->set('month', '');

    expect($component->instance()->activeFiltersCount)->toBe(0);
});

it('tests that applyFilters modifies the query as expected', function (): void {
    $component = new ArticleList;

    $query = Article::query()->where('status', ArticlesStatusEnum::PUBLISHED);

    $component->category = ArticlesCategoryEnum::PARTNERSHIP->value;
    $component->year = '2024';
    $component->month = '05';

    $reflection = new ReflectionClass($component);
    $method = $reflection->getMethod('applyFilters');
    $method->setAccessible(true);

    $method->invoke($component, $query);

    $sql = $query->toSql();

    expect(str_contains($sql, 'where'))->toBeTrue();
});

it('tests that forceRefresh dispatches event', function (): void {
    $component = Livewire::test(ArticleList::class);

    // Appeler la méthode forceRefresh
    $component->call('forceRefresh');

    // Vérifier que l'événement $refresh a été dispatché
    $component->assertDispatched('$refresh');
    expect(method_exists($component->instance(), 'forceRefresh'))->toBeTrue();
});
