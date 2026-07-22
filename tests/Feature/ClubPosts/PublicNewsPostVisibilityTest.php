<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Livewire\Public\Articles\ArticleList;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Public news post visibility
|--------------------------------------------------------------------------
|
| The status is the single source of truth for visibility: the public site
| serves a post if and only if it is published. Drafts and archived posts
| stay invisible everywhere — article page, list, sidebar and home page.
|
| Regression guard (issue #33): a second `is_public` flag used to hide
| freshly published posts, and the home page served drafts unfiltered.
|
*/

test('an anonymous visitor can read a published post', function (): void {
    $post = NewsPost::factory()->create([
        'slug' => 'article-bien-publie',
        'status' => NewsPostStatusEnum::PUBLISHED,
    ]);

    $this->get(route('public.clubPosts.show', $post->slug))->assertOk();
});

test('a draft is never served on the public site', function (): void {
    $draft = NewsPost::factory()->create([
        'slug' => 'brouillon-en-cours',
        'status' => NewsPostStatusEnum::DRAFT,
    ]);

    $this->get(route('public.clubPosts.show', $draft->slug))->assertNotFound();
});

test('an archived post is never served on the public site', function (): void {
    $archived = NewsPost::factory()->create([
        'slug' => 'vieil-article',
        'status' => NewsPostStatusEnum::ARCHIVED,
    ]);

    $this->get(route('public.clubPosts.show', $archived->slug))->assertNotFound();
});

test('the public list shows published posts only', function (): void {
    NewsPost::factory()->create([
        'title' => 'Article public',
        'status' => NewsPostStatusEnum::PUBLISHED,
    ]);
    NewsPost::factory()->create([
        'title' => 'Brouillon interne',
        'status' => NewsPostStatusEnum::DRAFT,
    ]);
    NewsPost::factory()->create([
        'title' => 'Article remisé',
        'status' => NewsPostStatusEnum::ARCHIVED,
    ]);

    Livewire::test(ArticleList::class)
        ->assertSee('Article public')
        ->assertDontSee('Brouillon interne')
        ->assertDontSee('Article remisé');
});

test('hidden posts never leak through the related-articles sidebar', function (): void {
    $visible = NewsPost::factory()->create([
        'slug' => 'article-visible',
        'status' => NewsPostStatusEnum::PUBLISHED,
        'category' => NewsPostCategoryEnum::COMPETITION,
    ]);

    NewsPost::factory()->create([
        'title' => 'Titre confidentiel',
        'slug' => 'brouillon-voisin',
        'status' => NewsPostStatusEnum::DRAFT,
        'category' => NewsPostCategoryEnum::COMPETITION,
    ]);

    $this->get(route('public.clubPosts.show', $visible->slug))
        ->assertOk()
        ->assertDontSee('Titre confidentiel', escape: false);
});

test('the home page shows published posts only', function (): void {
    Club::factory()->ownClub()->create();

    NewsPost::factory()->create([
        'title' => 'Actu publiée',
        'status' => NewsPostStatusEnum::PUBLISHED,
    ]);
    NewsPost::factory()->create([
        'title' => 'Brouillon de la home',
        'status' => NewsPostStatusEnum::DRAFT,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Actu publiée')
        ->assertDontSee('Brouillon de la home');
});
