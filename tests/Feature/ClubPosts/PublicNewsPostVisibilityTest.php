<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Livewire\Public\Articles\ArticleList;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Public news post visibility
|--------------------------------------------------------------------------
|
| The public site must serve a post only when the committee published it AND
| marked it publicly visible. The admin UI shows a padlock on is_public=false
| posts and the edit form calls the toggle "Visible publiquement", so both
| flags are a promise made to the committee — the public routes have to keep it.
|
| Regression guard: before this, `show()` ran an unfiltered whereSlug() and
| served drafts and padlocked posts to anonymous visitors.
|
*/

test('an anonymous visitor can read a published, public post', function (): void {
    $post = NewsPost::factory()->create([
        'slug' => 'article-bien-publie',
        'status' => NewsPostStatusEnum::PUBLISHED,
        'is_public' => true,
    ]);

    $this->get(route('public.clubPosts.show', $post->slug))->assertOk();
});

test('a draft is never served on the public site', function (): void {
    $draft = NewsPost::factory()->create([
        'slug' => 'brouillon-en-cours',
        'status' => NewsPostStatusEnum::DRAFT,
        'is_public' => true,
    ]);

    $this->get(route('public.clubPosts.show', $draft->slug))->assertNotFound();
});

test('an archived post is never served on the public site', function (): void {
    $archived = NewsPost::factory()->create([
        'slug' => 'vieil-article',
        'status' => NewsPostStatusEnum::ARCHIVED,
        'is_public' => true,
    ]);

    $this->get(route('public.clubPosts.show', $archived->slug))->assertNotFound();
});

test('a padlocked post is never served on the public site, even published', function (): void {
    $memberOnly = NewsPost::factory()->create([
        'slug' => 'reserve-aux-membres',
        'status' => NewsPostStatusEnum::PUBLISHED,
        'is_public' => false,
    ]);

    $this->get(route('public.clubPosts.show', $memberOnly->slug))->assertNotFound();
});

test('the public list shows published, public posts only', function (): void {
    NewsPost::factory()->create([
        'title' => 'Article public',
        'status' => NewsPostStatusEnum::PUBLISHED,
        'is_public' => true,
    ]);
    NewsPost::factory()->create([
        'title' => 'Brouillon interne',
        'status' => NewsPostStatusEnum::DRAFT,
        'is_public' => false,
    ]);
    NewsPost::factory()->create([
        'title' => 'Note aux membres',
        'status' => NewsPostStatusEnum::PUBLISHED,
        'is_public' => false,
    ]);

    Livewire::test(ArticleList::class)
        ->assertSee('Article public')
        ->assertDontSee('Brouillon interne')
        ->assertDontSee('Note aux membres');
});

test('hidden posts never leak through the related-articles sidebar', function (): void {
    $visible = NewsPost::factory()->create([
        'slug' => 'article-visible',
        'status' => NewsPostStatusEnum::PUBLISHED,
        'is_public' => true,
        'category' => NewsPostCategoryEnum::COMPETITION,
    ]);

    NewsPost::factory()->create([
        'title' => 'Titre confidentiel',
        'slug' => 'brouillon-voisin',
        'status' => NewsPostStatusEnum::DRAFT,
        'is_public' => false,
        'category' => NewsPostCategoryEnum::COMPETITION,
    ]);

    $this->get(route('public.clubPosts.show', $visible->slug))
        ->assertOk()
        ->assertDontSee('Titre confidentiel', escape: false);
});
