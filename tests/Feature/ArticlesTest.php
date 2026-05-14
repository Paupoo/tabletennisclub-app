<?php

declare(strict_types=1);

use App\Enums\NewsPostStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubPosts\NewsPost;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

describe('Articles index', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('admin.website.articles.index'))
            ->assertRedirect(route('login'));
    });

    it('is accessible to admins', function (): void {
        NewsPost::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.website.articles.index'))
            ->assertOk();
    });

    it('filters articles by status', function (): void {
        NewsPost::factory()->create(['status' => NewsPostStatusEnum::PUBLISHED, 'title' => 'Public Article']);
        NewsPost::factory()->create(['status' => NewsPostStatusEnum::DRAFT, 'title' => 'Draft Article']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.index')
            ->set('status', 'published')
            ->assertSee('Public Article')
            ->assertDontSee('Draft Article');
    });

    it('filters articles by search', function (): void {
        NewsPost::factory()->create(['title' => 'Unique Searchable Title']);
        NewsPost::factory()->create(['title' => 'Other Article']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.index')
            ->set('search', 'Unique Searchable')
            ->assertSee('Unique Searchable Title')
            ->assertDontSee('Other Article');
    });

    it('publishes an article inline', function (): void {
        $article = NewsPost::factory()->create(['status' => NewsPostStatusEnum::DRAFT]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.index')
            ->call('publish', $article->id);

        expect($article->fresh()->status)->toBe(NewsPostStatusEnum::PUBLISHED);
    });

    it('archives an article inline', function (): void {
        $article = NewsPost::factory()->create(['status' => NewsPostStatusEnum::PUBLISHED]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.index')
            ->call('archive', $article->id);

        expect($article->fresh()->status)->toBe(NewsPostStatusEnum::ARCHIVED);
    });

    it('soft deletes an article', function (): void {
        $article = NewsPost::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.index')
            ->call('confirmDelete', $article->id)
            ->call('delete');

        expect(NewsPost::find($article->id))->toBeNull();
        expect(NewsPost::withTrashed()->find($article->id))->not->toBeNull();
    });
});

describe('Articles create', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('admin.website.articles.create'))
            ->assertRedirect(route('login'));
    });

    it('saves article as draft', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit')
            ->set('title', 'My New Article')
            ->set('content', 'Some content here')
            ->set('category', 'News')
            ->set('status', 'draft')
            ->call('save');

        $post = NewsPost::where('title', 'My New Article')->first();
        expect($post)->not->toBeNull();
        expect($post->status)->toBe(NewsPostStatusEnum::DRAFT);
    });

    it('publishes a new article', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit')
            ->set('title', 'Published Article')
            ->set('content', 'Some content here')
            ->set('category', 'News')
            ->set('status', 'published')
            ->call('save');

        $post = NewsPost::where('title', 'Published Article')->first();
        expect($post->status)->toBe(NewsPostStatusEnum::PUBLISHED);
    });

    it('auto-generates slug from title on create', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit')
            ->set('title', 'Mon Bel Article')
            ->assertSet('slug', 'mon-bel-article');
    });

    it('validates required fields', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit')
            ->set('title', '')
            ->set('content', '')
            ->set('category', '')
            ->call('save')
            ->assertHasErrors(['title', 'content', 'category']);
    });
});

describe('Articles edit', function (): void {
    it('loads existing article data including status', function (): void {
        $article = NewsPost::factory()->create([
            'title' => 'Existing Article',
            'content' => 'Existing content',
            'category' => 'News',
            'status' => NewsPostStatusEnum::PUBLISHED,
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit', ['newsPost' => $article])
            ->assertSet('title', 'Existing Article')
            ->assertSet('content', 'Existing content')
            ->assertSet('status', 'published');
    });

    it('can change status to archived', function (): void {
        $article = NewsPost::factory()->create(['status' => NewsPostStatusEnum::PUBLISHED]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit', ['newsPost' => $article])
            ->set('status', 'archived')
            ->call('save');

        expect($article->fresh()->status)->toBe(NewsPostStatusEnum::ARCHIVED);
    });
});
