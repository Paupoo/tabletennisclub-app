<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

pest()->group('website', 'permissions');

/*
| What the club publishes, read by the committee — drafts included, since a
| draft is what is about to go out in the club's name. The editors stay with
| the website délégation.
*/

beforeEach(function (): void {
    $this->draft = NewsPost::factory()->create([
        'title' => 'Brouillon du comité',
        'content' => 'Texte encore confidentiel.',
        'status' => NewsPostStatusEnum::DRAFT,
    ]);
    $this->published = NewsPost::factory()->create(['title' => 'Article en ligne']);
    $this->event = EventPost::factory()->create([
        'title' => 'Souper du club',
        'status' => EventPostStatusEnum::DRAFT,
    ]);

    $this->reader = User::factory()->isCommitteeMember()->create();
    $this->delegate = User::factory()->withRole(Role::WEBSITE)->create();
});

it('opens both lists to the committee, and keeps the editor closed', function (): void {
    $this->actingAs($this->reader)->get(route('admin.website.articles.index'))->assertOk();
    $this->actingAs($this->reader)->get(route('admin.website.events.index'))->assertOk();
    $this->actingAs($this->reader)->get(route('admin.website.articles.create'))->assertForbidden();
    $this->actingAs($this->reader)->get(route('admin.website.articles.edit', $this->draft->slug))->assertForbidden();
});

describe('the articles', function (): void {
    it('lists them for a reader without a way to change them', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::website.articles.index')
            ->assertSee('Brouillon du comité')
            ->assertSee(route('public.clubPosts.show', $this->published->slug))
            ->assertSee('openPreview(' . $this->draft->id . ')')
            ->assertDontSee(route('admin.website.articles.create'))
            ->assertDontSee(route('admin.website.articles.edit', $this->draft->slug))
            ->assertDontSee('publish(' . $this->draft->id . ')')
            ->assertDontSee('confirmDelete(' . $this->draft->id . ')');
    });

    it('lets a reader read a draft', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::website.articles.index')
            ->call('openPreview', $this->draft->id)
            ->assertSee('Texte encore confidentiel.');
    });

    it('refuses every write to a reader', function (string $method, array $arguments): void {
        Livewire::actingAs($this->reader)
            ->test('pages::website.articles.index')
            ->set('selected', [$this->draft->id])
            ->call($method, ...$arguments)
            ->assertForbidden();

        expect($this->draft->fresh()->status)->toBe(NewsPostStatusEnum::DRAFT);
    })->with([
        'publish' => ['publish', [1]],
        'archive' => ['archive', [1]],
        'confirmDelete' => ['confirmDelete', [1]],
        'bulkPublish' => ['bulkPublish', []],
        'confirmBulkArchive' => ['confirmBulkArchive', []],
    ]);
});

describe('the events', function (): void {
    it('lists them for a reader without a way to change them', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::website.events.index')
            ->assertSee('Souper du club')
            ->assertDontSee('publish(' . $this->event->id . ')')
            ->assertDontSee('confirmDelete(' . $this->event->id . ')');
    });

    it('opens an event for a reader with its fields locked and nothing to save', function (): void {
        $html = Livewire::actingAs($this->reader)
            ->test('pages::website.events.index')
            ->call('openEdit', $this->event->id)
            ->html();

        expect($html)
            ->toMatch('/<input[^>]*wire:model="editTitle"[^>]*disabled/')
            ->not->toContain('wire:click="saveEdit"');
    });

    it('refuses every write to a reader', function (string $method, array $arguments): void {
        Livewire::actingAs($this->reader)
            ->test('pages::website.events.index')
            ->set('selected', [$this->event->id])
            ->call($method, ...$arguments)
            ->assertForbidden();

        expect($this->event->fresh()->status)->toBe(EventPostStatusEnum::DRAFT);
    })->with([
        'publish' => ['publish', [1]],
        'archive' => ['archive', [1]],
        'confirmDelete' => ['confirmDelete', [1]],
        'saveEdit' => ['saveEdit', []],
        'bulkPublish' => ['bulkPublish', []],
        'confirmBulkArchive' => ['confirmBulkArchive', []],
    ]);
});
