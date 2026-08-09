<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
});

it('articles list page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.website.articles.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Articles');
});

it('existing article appears in the articles list', function (): void {
    NewsPost::factory()->create(['title' => 'Mon article de test Bruxelles']);
    $this->actingAs($this->admin);

    visit(route('admin.website.articles.index'))
        ->assertSee('Mon article de test Bruxelles');
});

it('article create form is accessible', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.website.articles.create'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Nouvel article');
});

it('creates an article and redirects to list', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.website.articles.create'))
        ->type('input[id$="title"]', 'Article créé en test')
        ->type('input[id$="slug"]', 'article-cree-en-test')
        ->select('select[id$="category"]', 'News')
        ->select('select[id$="status"]', 'published');

    // Target the articles edit component (not notification-bell which is first)
    $page->script(
        'const comp = Livewire.all().find(c => c.name && c.name.includes("articles"));'
        . ' if (comp) comp.$wire.set("content", "Contenu de l\'article créé par le test.");'
    );

    $page->click('Enregistrer')
        ->assertSee('Articles');
});

it('public articles page shows published articles', function (): void {
    NewsPost::factory()->create(['title' => 'Article public de test', 'status' => 'published']);

    visit(route('public.clubPosts.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Article public de test');
});
