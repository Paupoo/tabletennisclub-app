<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Audit log — filter dropdowns
|--------------------------------------------------------------------------
|
| The action list was written by hand and stopped at created/updated/deleted,
| so the bespoke events had a label and no way to be filtered on. And the lists
| were ordered with PHP's byte-by-byte sort, which files every accented word
| after Z — « Équipe » landed past « Saison ».
|
*/

function auditFilters(User $supervisor): Testable
{
    return Livewire::actingAs($supervisor)->test('pages::club-admin.audit.index');
}

it('offers a bespoke action once it appears in the log', function (): void {
    $admin = User::factory()->isAdmin()->create();

    activity()
        ->performedOn(User::factory()->create())
        ->causedBy($admin)
        ->event('roles_changed')
        ->log('roles_changed');

    $ids = collect(auditFilters($admin)->viewData('eventOptions'))->pluck('id');

    expect($ids)->toContain('roles_changed');
});

it('offers no action the log does not hold', function (): void {
    $admin = User::factory()->isAdmin()->create();
    Room::factory()->create();

    $ids = collect(auditFilters($admin)->viewData('eventOptions'))->pluck('id');

    // Nothing has been deleted, so « Deleted » has nothing to show.
    expect($ids)->toContain('created')->not->toContain('deleted');
});

it('narrows the list to the bespoke action when it is picked', function (): void {
    $admin = User::factory()->isAdmin()->create();
    Room::factory()->create();

    activity()
        ->performedOn(User::factory()->create())
        ->causedBy($admin)
        ->event('roles_changed')
        ->log('roles_changed');

    expect(auditFilters($admin)->set('eventFilter', 'roles_changed')->viewData('activities')->total())
        ->toBe(1);
});

/*
 * « Équipe » before « Membre », and « Saison » before « Salle ». A byte-by-byte
 * sort returns Membre, Saison, Salle, Équipe: the accent alone sends the entry
 * to the bottom of a list an operator reads top to bottom.
 */
it('orders the item types the way French reads them, accents included', function (): void {
    $admin = User::factory()->isAdmin()->create();

    $season = Season::factory()->create();
    Room::factory()->create();
    Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => League::factory()->create(['season_id' => $season->id])->id,
    ]);

    $names = collect(auditFilters($admin)->viewData('modelOptions'))->pluck('name');

    // É collates as E, so « Équipe » opens the list; « Saison » comes before
    // « Salle » on the third letter. Byte order would give the reverse of both.
    expect($names->values()->all())->toBe(['Équipe', 'Ligue', 'Membre', 'Paramètre', 'Saison', 'Salle']);
});

it('orders the authors on the name the dropdown shows', function (): void {
    $zoe = User::factory()->isAdmin()->create(['first_name' => 'Zoé', 'last_name' => 'Aaron']);
    $emile = User::factory()->isAdmin()->create(['first_name' => 'Émile', 'last_name' => 'Zwart']);

    $this->actingAs($zoe);
    Room::factory()->create();

    $this->actingAs($emile);
    Room::factory()->create();

    $names = collect(auditFilters($zoe)->viewData('causerOptions'))->pluck('name');

    expect($names->all())->toBe(['Émile Zwart', 'Zoé Aaron']);
});
