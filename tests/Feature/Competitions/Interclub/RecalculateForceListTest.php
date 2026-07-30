<?php

declare(strict_types=1);

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\LeagueCategory;
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

it('assigns a block force index ordered by ranking, E6 and NC merged', function (): void {
    $b2 = makeCompetitor('B2', ['force_list' => null]);
    $c4 = makeCompetitor('C4', ['force_list' => null]);
    $e6 = makeCompetitor('E6', ['force_list' => null]);
    $nc = makeCompetitor('NC', ['force_list' => null]);

    RecalculateForceListAction::handle();

    // B2(1) → 1, C4(1) → 2, E6-NC(2) → 4 shared by E6 and NC.
    expect($b2->fresh()->force_list)->toBe(1);
    expect($c4->fresh()->force_list)->toBe(2);
    expect($e6->fresh()->force_list)->toBe(4);
    expect($nc->fresh()->force_list)->toBe(4);
});

it('gives every competitor of the same ranking the same force index', function (): void {
    $a = makeCompetitor('C4', ['last_name' => 'AAA', 'force_list' => null]);
    $b = makeCompetitor('C4', ['last_name' => 'BBB', 'force_list' => null]);
    $c = makeCompetitor('C4', ['last_name' => 'CCC', 'force_list' => null]);

    RecalculateForceListAction::handle();

    // Single block of 3 → cumulative 3, shared.
    expect($a->fresh()->force_list)->toBe(3)
        ->and($b->fresh()->force_list)->toBe(3)
        ->and($c->fresh()->force_list)->toBe(3);
});

it('leaves NA competitors out of the force list', function (): void {
    $na = makeCompetitor('NA', ['force_list' => null]);
    $c4 = makeCompetitor('C4', ['force_list' => null]);

    RecalculateForceListAction::handle();

    expect($na->fresh()->force_list)->toBeNull()
        ->and($c4->fresh()->force_list)->toBe(1);
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

it('assigns force_list_women only to women, ordered by ranking', function (): void {
    $womanB2 = makeCompetitor('B2', ['gender' => Gender::WOMEN, 'force_list_women' => null]);
    $man = makeCompetitor('C0', ['gender' => Gender::MEN]);
    $womanD4 = makeCompetitor('D4', ['gender' => Gender::WOMEN, 'force_list_women' => null]);

    RecalculateForceListAction::handle();

    expect($womanB2->fresh()->force_list_women)->toBe(1);
    expect($womanD4->fresh()->force_list_women)->toBe(2);
    expect($man->fresh()->force_list_women)->toBeNull();
    // The general list still ranks everyone.
    expect($man->fresh()->force_list)->not->toBeNull();
});

it('assigns force_list_veterans only to players >= 40 at season end, ordered by ranking', function (): void {
    $season = Season::current();
    $vetC0 = makeCompetitor('C0', ['birthdate' => $season->end_at->copy()->subYears(50)]);
    $vetD4 = makeCompetitor('D4', ['birthdate' => $season->end_at->copy()->subYears(45)]);
    $young = makeCompetitor('B2', ['birthdate' => $season->end_at->copy()->subYears(20)]);

    RecalculateForceListAction::handle();

    expect($vetC0->fresh()->force_list_veterans)->toBe(1);
    expect($vetD4->fresh()->force_list_veterans)->toBe(2);
    expect($young->fresh()->force_list_veterans)->toBeNull();
});

it('places a woman veteran on all three lists at once', function (): void {
    $season = Season::current();
    $womanVet = makeCompetitor('C0', [
        'gender' => Gender::WOMEN,
        'birthdate' => $season->end_at->copy()->subYears(50),
    ]);

    RecalculateForceListAction::handle();

    $fresh = $womanVet->fresh();
    expect($fresh->force_list)->not->toBeNull();
    expect($fresh->force_list_women)->not->toBeNull();
    expect($fresh->force_list_veterans)->not->toBeNull();
});

it('recalculates the women list when a competitor gender changes', function (): void {
    $player = makeCompetitor('C0', ['gender' => Gender::MEN]);
    RecalculateForceListAction::handle();
    expect($player->fresh()->force_list_women)->toBeNull();

    $player->update(['gender' => Gender::WOMEN]);

    expect($player->fresh()->force_list_women)->toBe(1);
});

it('recalculates the veterans list when a competitor birthdate changes', function (): void {
    $season = Season::current();
    $player = makeCompetitor('C0', ['birthdate' => $season->end_at->copy()->subYears(20)]);
    RecalculateForceListAction::handle();
    expect($player->fresh()->force_list_veterans)->toBeNull();

    $player->update(['birthdate' => $season->end_at->copy()->subYears(50)]);

    expect($player->fresh()->force_list_veterans)->toBe(1);
});

it('limits interclub eligibility to ranked members with a competitive licence', function (): void {
    $season = Season::current();

    $eligible = makeCompetitor('C4');
    $na = makeCompetitor('NA');

    // Non-competitive licence for the season: eligible only requires a validated
    // or paid *competitive* licence, so this member is out.
    $recreative = User::withoutEvents(fn () => User::factory()->create(['ranking' => 'C4']));
    Subscription::withoutEvents(fn () => Subscription::factory()->for($recreative)->create([
        'season_id' => $season->id, 'is_competitive' => false, 'status' => 'confirmed',
    ]));

    $eligibleIds = User::interclubEligible()->pluck('id')->all();

    expect($eligibleIds)->toContain($eligible->id)
        ->not->toContain($na->id)
        ->not->toContain($recreative->id);
});

it('maps league categories to the right force-list column and value', function (): void {
    $user = User::factory()->make([
        'force_list' => 5,
        'force_list_women' => 2,
        'force_list_veterans' => 3,
    ]);

    expect(User::forceListColumn('WOMEN'))->toBe('force_list_women')
        ->and(User::forceListColumn('VETERANS'))->toBe('force_list_veterans')
        ->and(User::forceListColumn('MEN'))->toBe('force_list')
        ->and(User::forceListColumn(null))->toBe('force_list');

    expect($user->forceListFor('WOMEN'))->toBe(2)
        ->and($user->forceListFor(LeagueCategory::VETERANS))->toBe(3)
        ->and($user->forceListFor('MEN'))->toBe(5)
        ->and($user->forceListFor(null))->toBe(5);
});
