<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Page smoke test (parameterised routes)
|--------------------------------------------------------------------------
|
| Routes with {parameters} cannot be enumerated blindly because they require
| real bound models (and their dependencies). Each route below gets a dedicated
| test that builds the model via its factory and asserts the page renders.
|
| Parameterless routes are covered by PageSmokeTest.php.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()
        ->isAdmin()
        ->isCommitteeMember()
        ->create(['is_active' => true]);

    $this->season = makeActiveSeason();
});

function smokeGet(string $route, mixed ...$params): TestResponse
{
    return test()->actingAs(test()->admin)->get(route($route, $params));
}

it('renders user edit', function (): void {
    $user = User::factory()->create();
    smokeGet('admin.users.edit', $user)->assertOk();
});

it('user edit form mounts without TypeError when has_key attribute is missing (regression)', function (): void {
    // Reproduces the TypeError that occurs when the has_key migration has not run yet:
    // the attribute is absent from the model → getAttribute() returns null →
    // assigning null to a typed bool Livewire property throws TypeError.
    $user = User::factory()->isNotCompetitor()->create();

    $attrs = $user->getAttributes();
    unset($attrs['has_key']);
    $user->setRawAttributes($attrs, true);

    Livewire::actingAs(test()->admin)
        ->test('pages::club-admin.users.form', ['user' => $user])
        ->assertOk();
});

it('renders my-space pages for the user', function (string $routeName): void {
    smokeGet($routeName, $this->admin)->assertOk();
})->with([
    'admin.user.profile',
    'admin.user.settings',
    'admin.user.teams',
    'admin.user.calendar',
    'admin.user.event-subscription',
    'admin.user.registration-management',
]);

it('renders room edit', function (): void {
    $room = Room::factory()->create();
    smokeGet('admin.rooms.edit', $room)->assertOk();
});

it('renders table edit', function (): void {
    $table = Table::factory()->create();
    smokeGet('admin.tables.edit', $table)->assertOk();
});

it('renders meeting show and edit', function (string $routeName): void {
    $meeting = Meeting::factory()->create();
    smokeGet($routeName, $meeting)->assertOk();
})->with([
    'admin.meetings.show',
    'admin.meetings.edit',
]);

it('renders tournament pages', function (string $routeName): void {
    $tournament = Tournament::factory()->create();
    smokeGet($routeName, $tournament)->assertOk();
})->with([
    'admin.tournaments.live-center',
    'admin.tournaments.wizard.edit',
    'tournament.registration.confirmed',
]);

it('renders tournament table score', function (): void {
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create();
    smokeGet('tournament.table.score', $tournament, $table)->assertOk();
});

it('renders interclub team show and edit', function (string $routeName): void {
    $team = Team::factory()->create();
    smokeGet($routeName, $team)->assertOk();
})->with([
    'admin.interclubs.teams.show',
    'admin.interclubs.teams.edit',
]);

it('renders news post edit', function (): void {
    $post = NewsPost::factory()->create();
    smokeGet('admin.website.articles.edit', $post)->assertOk();
});

it('renders public news post show', function (): void {
    $post = NewsPost::factory()->create();
    smokeGet('public.clubPosts.show', $post)->assertOk();
});

it('renders bar order payment', function (): void {
    // BarOrder has no factory; create directly with its fillable attributes.
    $order = BarOrder::create([
        'created_by' => $this->admin->id,
        'total_price' => 0,
        'is_paid' => false,
    ]);

    smokeGet('bar.payment.show', $order)->assertOk();
});
