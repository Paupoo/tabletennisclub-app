<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Livewire\Public\Articles\ArticleList;
use App\Models\ClubPosts\NewsPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ArticleList', function (): void {
    it('renders without error', function (): void {
        Livewire::test(ArticleList::class)->assertOk();
    });

    it('only shows published articles', function (): void {
        NewsPost::factory()->create(['title' => 'Published Article', 'status' => NewsPostStatusEnum::PUBLISHED]);
        NewsPost::factory()->create(['title' => 'Draft Article', 'status' => NewsPostStatusEnum::DRAFT]);

        Livewire::test(ArticleList::class)
            ->assertSee('Published Article')
            ->assertDontSee('Draft Article');
    });

    it('can filter by category', function (): void {
        $category = NewsPostCategoryEnum::cases()[0];

        NewsPost::factory()->create([
            'title' => 'Category Article',
            'status' => NewsPostStatusEnum::PUBLISHED,
            'category' => $category->value,
        ]);

        Livewire::test(ArticleList::class)
            ->set('category', $category->value)
            ->assertSee('Category Article');
    });

    it('can filter by year', function (): void {
        NewsPost::factory()->create([
            'title' => 'Old Article',
            'status' => NewsPostStatusEnum::PUBLISHED,
            'created_at' => now()->subYears(2),
        ]);
        NewsPost::factory()->create([
            'title' => 'Current Article',
            'status' => NewsPostStatusEnum::PUBLISHED,
            'created_at' => now(),
        ]);

        Livewire::test(ArticleList::class)
            ->set('year', (string) now()->year)
            ->assertSee('Current Article')
            ->assertDontSee('Old Article');
    });

    it('can clear all filters', function (): void {
        Livewire::test(ArticleList::class)
            ->set('category', 'some-category')
            ->set('year', '2023')
            ->call('clearAllFilters')
            ->assertSet('category', '')
            ->assertSet('year', '')
            ->assertSet('sort', 'desc');
    });

    it('can clear a specific filter', function (): void {
        Livewire::test(ArticleList::class)
            ->set('category', 'some-category')
            ->set('year', '2023')
            ->call('clearFilter', 'category')
            ->assertSet('category', '')
            ->assertSet('year', '2023');
    });

    it('counts active filters correctly', function (): void {
        Livewire::test(ArticleList::class)
            ->set('category', 'some-category')
            ->set('year', '2023')
            ->assertSet('activeFiltersCount', 2);
    });
});
