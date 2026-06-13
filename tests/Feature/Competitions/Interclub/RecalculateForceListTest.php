<?php

declare(strict_types=1);

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Helper: create a competitor user without triggering observers mid-setup.
function makeCompetitor(string $ranking, array $attrs = []): User
{
    $season = Season::current();
    $user = User::withoutEvents(fn () => User::factory()->create(array_merge(['ranking' => $ranking], $attrs)));
    if ($season !== null) {
        Subscription::withoutEvents(fn () => Subscription::factory()->for($user)->create([
            'season_id' => $season->id, 'is_competitive' => true,
        ]));
    }

    return $user;
}

beforeEach(function (): void {
    Season::factory()->create(['is_active' => true]);
});

it('assigns force_list ordered by ranking', function (): void {
    $b2 = makeCompetitor('B2', ['force_list' => null]);
    $c4 = makeCompetitor('C4', ['force_list' => null]);
    $e6 = makeCompetitor('E6', ['force_list' => null]);
    $nc = makeCompetitor('NC', ['force_list' => null]);

    RecalculateForceListAction::handle();

    expect($b2->fresh()->force_list)->toBeLessThan($c4->fresh()->force_list);
    expect($c4->fresh()->force_list)->toBeLessThan($e6->fresh()->force_list);
    expect($e6->fresh()->force_list)->toBeLessThan($nc->fresh()->force_list);
});

it('excludes non-competitors from force_list calculation', function (): void {
    makeCompetitor('C4', ['force_list' => null]);
    $nonComp = User::withoutEvents(fn () => User::factory()->create(['ranking' => 'B2', 'force_list' => null]));

    RecalculateForceListAction::handle();

    expect($nonComp->fresh()->force_list)->toBeNull();
});

it('recalculates when a user becomes a competitor', function (): void {
    $season = Season::current();
    $strong = makeCompetitor('B2', ['force_list' => null]);
    $new = User::withoutEvents(fn () => User::factory()->create(['ranking' => 'C4', 'force_list' => null]));

    // Becoming a competitor: create a competitive subscription (triggers SubscriptionObserver)
    Subscription::factory()->for($new)->create([
        'season_id' => $season->id, 'is_competitive' => true,
    ]);

    expect($strong->fresh()->force_list)->not->toBeNull();
    expect($new->fresh()->force_list)->not->toBeNull();
    expect($strong->fresh()->force_list)->toBeLessThan($new->fresh()->force_list);
});

it('recalculates when a competitor ranking changes', function (): void {
    $a = makeCompetitor('D2', ['force_list' => null]);
    $b = makeCompetitor('C4', ['force_list' => null]);

    $a->update(['ranking' => 'B0']);

    expect($a->fresh()->force_list)->toBeLessThan($b->fresh()->force_list);
});

it('does not recalculate when an unrelated field changes', function (): void {
    $user = makeCompetitor('B2', ['force_list' => 1]);
    $initialForceList = $user->force_list;

    $user->update(['first_name' => 'Nouveau']);

    expect($user->fresh()->force_list)->toBe($initialForceList);
});

it('recalculates when a competitor is deleted', function (): void {
    $strong = makeCompetitor('B2', ['force_list' => null]);
    $weak = makeCompetitor('D4', ['force_list' => null]);
    $mid = makeCompetitor('C0', ['force_list' => null]);

    RecalculateForceListAction::handle();

    $strong->delete();

    expect(User::competitor()->whereNotNull('force_list')->count())
        ->toBe(2);

    expect($mid->fresh()->force_list)->toBeLessThan($weak->fresh()->force_list);
});

it('does not recalculate when a non-competitor is deleted', function (): void {
    $comp = makeCompetitor('C0', ['force_list' => 1]);
    $nonComp = User::withoutEvents(fn () => User::factory()->create(['force_list' => null]));

    $nonComp->delete();

    expect($comp->fresh()->force_list)->toBe(1);
});

it('admin can recalculate force list via the users page', function (): void {
    $admin = User::factory()->isAdmin()->create();
    makeCompetitor('B2', ['force_list' => null]);
    makeCompetitor('D4', ['force_list' => null]);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.index')
        ->call('recalculateForceList');

    expect(User::competitor()->whereNull('force_list')->count())->toBe(0);
});

it('regular user cannot call recalculateForceList', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.index')
        ->call('recalculateForceList')
        ->assertForbidden();
});
