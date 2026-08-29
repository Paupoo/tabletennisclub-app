<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;

/*
|--------------------------------------------------------------------------
| Admin navigation visibility
|--------------------------------------------------------------------------
|
| A délégation grants operational rights to any member, committee or not. The
| menus leading to those rights used to be nested under a blanket users.view
| gate (the committee baseline), so a plain member holding, say, the CONTACTS
| duty could reach the pages by URL but never saw the links. Each submenu now
| answers to its own permissions.
|
*/

function renderAdminNavigation(User $user): string
{
    test()->actingAs($user);

    return (string) test()->blade('<x-admin.navigation :user="$user" />', ['user' => $user]);
}

it('hides every back-office menu from a plain member', function (): void {
    $html = renderAdminNavigation(User::factory()->create());

    expect($html)
        ->not->toContain(route('admin.website.contacts.index'))
        ->not->toContain(route('admin.treasury.payments'))
        ->not->toContain(route('admin.users.index'))
        ->not->toContain(route('admin.meetings.index'));
});

it('shows the contacts menu to a contacts delegate who is not on the committee', function (): void {
    $delegate = User::factory()->withRole(Role::CONTACTS)->create();

    expect(renderAdminNavigation($delegate))
        ->toContain(route('admin.website.contacts.index'))
        // …without leaking committee-only menus it holds no permission for.
        ->not->toContain(route('admin.users.index'))
        ->not->toContain(route('admin.treasury.payments'));
});

it('shows the treasury menu to a treasury delegate who is not on the committee', function (): void {
    $delegate = User::factory()->withRole(Role::TREASURY)->create();

    expect(renderAdminNavigation($delegate))
        ->toContain(route('admin.treasury.payments'))
        ->not->toContain(route('admin.users.index'));
});

it('shows the events menu to a meetings delegate who is not on the committee', function (): void {
    $delegate = User::factory()->withRole(Role::MEETINGS)->create();

    expect(renderAdminNavigation($delegate))
        ->toContain(route('admin.meetings.index'))
        ->not->toContain(route('admin.users.index'));
});

it('shows the rooms menu to a facilities delegate who is not on the committee', function (): void {
    $delegate = User::factory()->withRole(Role::FACILITIES)->create();

    expect(renderAdminNavigation($delegate))
        ->toContain(route('admin.rooms.index'))
        ->not->toContain(route('admin.users.index'));
});

it('still shows the contacts and members menus to a committee member', function (): void {
    $committee = User::factory()->withRole(Role::COMMITTEE)->create();

    expect(renderAdminNavigation($committee))
        ->toContain(route('admin.website.contacts.index'))
        ->toContain(route('admin.users.index'));
});

/*
 * A captain is a relation (teams.captain_id), never a délégation. The Gates
 * guarding the selections and results screens say so explicitly, but the menu
 * asked for the selections.manage / results.manage permissions instead — so a
 * plain captain could use the pages and never find them.
 */
it('shows the selections and results menus to a plain captain holding no permission', function (): void {
    $captain = User::factory()->isCompetitor()->create();

    $season = Season::factory()->create(['is_active' => true]);
    $league = League::factory()->create([
        'season_id' => $season->id,
        'category' => 'MEN',
    ]);
    Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => Club::factory()->ownClub()->create()->id,
        'captain_id' => $captain->id,
    ]);

    expect(renderAdminNavigation($captain))
        ->toContain(route('admin.interclubs.captain-selection'))
        ->toContain(route('admin.interclubs.results'))
        // …without opening the season configuration, which stays committee-only.
        ->not->toContain(route('admin.interclubs.teams'))
        ->not->toContain(route('admin.users.index'));
});

it('keeps the interclubs menu hidden from a member who captains nothing', function (): void {
    expect(renderAdminNavigation(User::factory()->isCompetitor()->create()))
        ->not->toContain(route('admin.interclubs.captain-selection'))
        ->not->toContain(route('admin.interclubs.results'));
});
