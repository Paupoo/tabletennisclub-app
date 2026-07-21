<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| The three surfaces that carried the least authorization of the whole app:
|
| - the website admin had NO internal check on any of its four components
| - meetings repeated the same canManage() in three of them
| - the bar was reachable in full by any signed-in member — catalogue, stock,
|   orders, cash sheet — and had no test at all
|
| The existing suites did not catch the gap: they act as administrators, who hold
| every permission, so they pass either way.
*/

beforeEach(function (): void {
    $this->committeeOnly = User::factory()->isCommitteeMember()->create();
    $this->member = User::factory()->create();
});

describe('the bar, previously open to every member', function (): void {
    it('turns a plain member away from the whole module', function (string $routeName): void {
        $this->actingAs($this->member)->get(route($routeName))->assertForbidden();
    })->with([
        'bar.index',
        'bar.orders.index',
        'bar.products.index',
        'bar.categories.index',
        'bar.cashSheet.index',
    ]);

    it('turns a committee member away too — it is a duty, not a rank', function (): void {
        $this->actingAs($this->committeeOnly)->get(route('bar.index'))->assertForbidden();
    });

    it('opens to whoever holds the bar duty', function (): void {
        $barkeeper = User::factory()->withRole(Role::BAR)->create();

        expect($barkeeper->hasRole(Role::COMMITTEE->value))->toBeFalse();

        $this->actingAs($barkeeper)->get(route('bar.index'))->assertOk();
    });
});

describe('the website admin, previously guarded by route middleware alone', function (): void {
    it('keeps a committee member out of the article and event screens', function (string $routeName): void {
        $this->actingAs($this->committeeOnly)->get(route($routeName))->assertForbidden();
    })->with([
        'admin.website.articles.index',
        'admin.website.articles.create',
        'admin.website.events.index',
        'admin.website.spams.index',
    ]);

    it('keeps a plain member out as well', function (string $routeName): void {
        $this->actingAs($this->member)->get(route($routeName))->assertForbidden();
    })->with([
        'admin.website.articles.index',
        'admin.website.articles.create',
        'admin.website.events.index',
        'admin.website.spams.index',
        'admin.website.contacts.index',
    ]);

    it('opens the articles to the website delegate', function (): void {
        $this->actingAs(User::factory()->withRole(Role::WEBSITE)->create())
            ->get(route('admin.website.articles.index'))
            ->assertOk();
    });

    it('refuses to publish or delete an article without the duty', function (string $method): void {
        NewsPost::factory()->create();

        Livewire::actingAs($this->committeeOnly)
            ->test('pages::website.articles.index')
            ->call($method)
            ->assertForbidden();
    })->with(['bulkPublish', 'bulkArchive', 'delete']);

    it('refuses to open the article editor without the duty', function (): void {
        Livewire::actingAs($this->committeeOnly)
            ->test('pages::website.articles.edit')
            ->assertForbidden();
    });

    it('separates handling spam from writing articles', function (): void {
        $writer = User::factory()->withRole(Role::WEBSITE)->create();
        $triager = User::factory()->withRole(Role::CONTACTS)->create();

        $this->actingAs($writer)->get(route('admin.website.spams.index'))->assertForbidden();
        $this->actingAs($triager)->get(route('admin.website.spams.index'))->assertOk();
        $this->actingAs($triager)->get(route('admin.website.articles.index'))->assertForbidden();
    });
});

describe('meetings', function (): void {
    it('lets the committee read, and reserves managing to the delegate', function (): void {
        $delegate = User::factory()->withRole(Role::MEETINGS)->create();

        $this->actingAs($this->committeeOnly)->get(route('admin.meetings.index'))->assertOk();
        $this->actingAs($delegate)->get(route('admin.meetings.index'))->assertOk();
        $this->actingAs($this->member)->get(route('admin.meetings.index'))->assertForbidden();
    });

    it('withholds the management actions from a plain committee member', function (): void {
        $component = Livewire::actingAs($this->committeeOnly)->test('pages::club-events.meetings.index');

        expect($component->instance()->canManage)->toBeFalse();

        expect(Livewire::actingAs(User::factory()->withRole(Role::MEETINGS)->create())
            ->test('pages::club-events.meetings.index')
            ->instance()->canManage)->toBeTrue();
    });
});
