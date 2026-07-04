<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Back-office route authorization
|--------------------------------------------------------------------------
|
| The admin back-office relied on the navigation only *hiding* its links, while
| the underlying routes were reachable by URL for any authenticated member.
| This locks the pages down at the middleware level (with a defense-in-depth
| Gate inside the user form) and documents the surfaces left intentionally open.
|
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->member = User::factory()->create();                       // plain member, no roles
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
});

it('forbids a plain member on committee-only pages', function (string $routeName): void {
    $this->actingAs($this->member)->get(route($routeName))->assertForbidden();
})->with([
    'admin.users.index',
    'admin.users.create',
    'admin.users.registrations',
    'admin.subscriptions.roster',
    'admin.treasury.payments',
    'admin.treasury.transactions',
    'admin.tournaments.index',
    'admin.trainings.index',
    'admin.rooms.index',
    'admin.tables.index',
    'admin.interclubs.control-center',
    'admin.interclubs.teams',
    'admin.interclubs.clubs',
    'admin.interclubs.division-setup',
    'admin.interclubs.interclubs',
]);

it('forbids a plain member from editing another user (reported issue)', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->member)->get(route('admin.users.edit', $target))->assertForbidden();
});

it('allows an admin to edit a user', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.users.edit', $target))->assertOk();
});

// Selections & results self-authorize inside their component mount() (committee,
// captains, and selectors); a plain member is rejected there.
it('forbids a plain member on interclub selections & results', function (string $routeName): void {
    $this->actingAs($this->member)->get(route($routeName))->assertForbidden();
})->with([
    'admin.interclubs.captain-selection',
    'admin.interclubs.results',
]);

it('treats a team captain as a non-committee member on committee-only pages', function (): void {
    $captain = User::factory()->create();
    Team::factory()->create(['captain_id' => $captain->id, 'season_id' => $this->season->id]);

    // A captain is not a committee member: committee-only pages stay forbidden.
    $this->actingAs($captain)->get(route('admin.interclubs.control-center'))->assertForbidden();
});

it('forbids a plain member from the coach area but grants coaches and admins', function (): void {
    $coach = User::factory()->create(['is_coach' => true]);

    $this->actingAs($this->member)->get(route('coach.trainings'))->assertForbidden();
    expect(Gate::forUser($coach)->allows('access-coach-area'))->toBeTrue();
    expect(Gate::forUser($this->admin)->allows('access-coach-area'))->toBeTrue();
});

it('keeps self-scoped and bar pages open to any member', function (string $routeName): void {
    $this->actingAs($this->member)->get(route($routeName))->assertOk();
})->with([
    'admin.interclubs.my-matches',
    'admin.treasury.cash',
]);

it('refuses to mount the user form for a plain member (defense in depth)', function (): void {
    $target = User::factory()->create();

    Livewire::actingAs($this->member)
        ->test('pages::club-admin.users.form', ['user' => $target])
        ->assertForbidden();
});
