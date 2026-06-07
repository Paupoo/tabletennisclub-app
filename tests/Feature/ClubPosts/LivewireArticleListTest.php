<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Livewire\Public\Articles\ArticleList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ArticleList', function (): void {
    it('renders without error', function (): void {
        Livewire::test(ArticleList::class)->assertOk();
    });

    it('only shows published articles', function (): void {
        $season = Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);
        NewsPost::factory()->create(['title' => 'Published Article', 'status' => NewsPostStatusEnum::PUBLISHED, 'created_at' => now()]);
        NewsPost::factory()->create(['title' => 'Draft Article', 'status' => NewsPostStatusEnum::DRAFT, 'created_at' => now()]);

        Livewire::test(ArticleList::class)
            ->assertSee('Published Article')
            ->assertDontSee('Draft Article');
    });

    it('can filter by category', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);
        $category = NewsPostCategoryEnum::cases()[0];

        NewsPost::factory()->create([
            'title' => 'Category Article',
            'status' => NewsPostStatusEnum::PUBLISHED,
            'category' => $category->value,
            'created_at' => now(),
        ]);

        Livewire::test(ArticleList::class)
            ->set('category', $category->value)
            ->assertSee('Category Article');
    });

    it('defaults to active season and filters articles within its date range', function (): void {
        $activeSeason = Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->subMonths(6),
            'end_at' => now()->addMonths(6),
        ]);
        $oldSeason = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(2),
            'end_at' => now()->subYears(2)->addMonths(10),
        ]);

        NewsPost::factory()->create([
            'title' => 'Current Season Article',
            'status' => NewsPostStatusEnum::PUBLISHED,
            'created_at' => now(),
        ]);
        NewsPost::factory()->create([
            'title' => 'Old Season Article',
            'status' => NewsPostStatusEnum::PUBLISHED,
            'created_at' => now()->subYears(2),
        ]);

        Livewire::test(ArticleList::class)
            ->assertSee('Current Season Article')
            ->assertDontSee('Old Season Article');
    });

    it('can switch to a different season', function (): void {
        $activeSeason = Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->subMonths(6),
            'end_at' => now()->addMonths(6),
        ]);
        $oldSeason = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(2),
            'end_at' => now()->subYears(2)->addMonths(10),
        ]);

        NewsPost::factory()->create([
            'title' => 'Current Season Article',
            'status' => NewsPostStatusEnum::PUBLISHED,
            'created_at' => now(),
        ]);
        NewsPost::factory()->create([
            'title' => 'Old Season Article',
            'status' => NewsPostStatusEnum::PUBLISHED,
            'created_at' => now()->subYears(2),
        ]);

        Livewire::test(ArticleList::class)
            ->set('seasonId', $oldSeason->id)
            ->assertSee('Old Season Article')
            ->assertDontSee('Current Season Article');
    });

    it('can clear all filters', function (): void {
        $activeSeason = Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);
        NewsPost::factory()->create(['status' => NewsPostStatusEnum::PUBLISHED, 'created_at' => now()]);

        Livewire::test(ArticleList::class)
            ->set('category', 'some-category')
            ->call('clearAllFilters')
            ->assertSet('category', '')
            ->assertSet('sort', 'desc')
            ->assertSet('seasonId', $activeSeason->id);
    });

    it('can clear a specific filter', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);

        Livewire::test(ArticleList::class)
            ->set('category', 'some-category')
            ->call('clearFilter', 'category')
            ->assertSet('category', '');
    });

    it('counts active filters correctly', function (): void {
        $activeSeason = Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);
        $otherSeason = Season::factory()->create(['is_active' => false, 'start_at' => now()->subYears(2), 'end_at' => now()->subYears(2)->addMonths(10)]);

        Livewire::test(ArticleList::class)
            ->set('category', 'some-category')
            ->set('seasonId', $otherSeason->id)
            ->assertSet('activeFiltersCount', 2);
    });
});
