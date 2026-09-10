<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Models\TeamUser;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Team roster — audit trail
|--------------------------------------------------------------------------
|
| Adding or removing a player writes to team_user, and attach/detach/sync fire
| no Eloquent event on a plain pivot: the change a captain makes most often left
| no trace of its author nor of its content. TeamUser promotes the pivot to a
| model so the audit log sees it.
|
*/

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id]);

    $this->admin = User::factory()->isAdmin()->create();
    $this->player = User::factory()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
    ]);
});

/** @return Builder<Activity> */
function rosterActivities(string $event): Builder
{
    return Activity::query()
        ->where('subject_type', TeamUser::class)
        ->where('event', $event);
}

it('logs a player joining a team, with the author and both keys', function (): void {
    $this->actingAs($this->admin);

    $this->team->users()->attach($this->player->id);

    $activity = rosterActivities('created')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->attribute_changes['attributes']['team_id'])->toBe($this->team->id)
        ->and($activity->attribute_changes['attributes']['user_id'])->toBe($this->player->id);
});

it('logs a player leaving a team, keeping both keys in the entry', function (): void {
    $this->team->users()->attach($this->player->id);

    $this->actingAs($this->admin);
    $this->team->users()->detach($this->player->id);

    $activity = rosterActivities('deleted')->latest('id')->first();

    // A deletion stores its content under `old`: the package moves it there and
    // drops `attributes` altogether.
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->attribute_changes['old']['team_id'])->toBe($this->team->id)
        ->and($activity->attribute_changes['old']['user_id'])->toBe($this->player->id);
});

it('logs both the arrival and the departure when the edit screen syncs a roster', function (): void {
    $leaving = User::factory()->create();
    $this->team->users()->attach($leaving->id);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('memberIds', [$this->player->id])
        ->call('save');

    expect($this->team->fresh()->users->pluck('id')->all())->toBe([$this->player->id]);

    expect(rosterActivities('created')->get()->pluck('attribute_changes.attributes.user_id'))
        ->toContain($this->player->id);

    expect(rosterActivities('deleted')->get()->pluck('attribute_changes.old.user_id'))
        ->toContain($leaving->id);
});

/*
 * The foreign keys cascade on delete, so without the observer the database
 * removes the roster itself — no model event, no entry, and no way to tell
 * afterwards who was in the team that was just deleted. This screen never
 * detached by hand.
 */
it('logs the roster of a team deleted without an explicit detach', function (): void {
    $this->team->users()->attach($this->player->id);
    rosterActivities('deleted')->delete();

    $this->actingAs($this->admin);
    Team::find($this->team->id)->delete();

    $activity = rosterActivities('deleted')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['old']['user_id'])->toBe($this->player->id)
        ->and($activity->attribute_changes['old']['team_id'])->toBe($this->team->id);
});

/*
 * The package moves a deletion's content to `old` and drops `attributes`; the
 * Details column only ever read `attributes`, so EVERY « deleted » row in the
 * log showed a dash. Asserted on a team name rather than on the roster's
 * foreign keys: a bare integer id appears all over the page — in pagination, in
 * dates, in the subject column — so a test built on one passes whether the
 * column is fixed or not.
 *
 * The list is narrowed to deletions because the team's own `created` entry
 * carries the same name and renders correctly either way.
 */
it('shows what a deletion contained, where the audit screen used to show a dash', function (): void {
    $doomed = Team::factory()->create([
        'name' => 'ZZTOP',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
    ]);

    $this->actingAs($this->admin);
    $doomed->delete();

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.audit.index')
        ->set('modelFilter', Team::class)
        ->set('eventFilter', 'deleted')
        ->assertSee('ZZTOP');
});

it('keeps the roster entries filterable by item type and by action', function (): void {
    $this->actingAs($this->admin);
    $this->team->users()->attach($this->player->id);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-admin.audit.index');

    expect(collect($component->viewData('modelOptions'))->pluck('id'))
        ->toContain(TeamUser::class);

    // The action filter offers created/updated/deleted, and a roster movement is
    // recorded as exactly one of those — no bespoke event to add to the list.
    expect(rosterActivities('created')->exists())->toBeTrue();
});

it('gives the audited roster model a human label on the audit screen', function (): void {
    $supervisor = User::factory()->isAdmin()->create();

    $component = Livewire::actingAs($supervisor)->test('pages::club-admin.audit.index');

    expect($component->instance()->subjectLabel(TeamUser::class))->not->toBe('TeamUser');
});
