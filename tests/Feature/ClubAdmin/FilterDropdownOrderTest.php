<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Filter dropdowns — reading order
|--------------------------------------------------------------------------
|
| PHP's sort compares byte by byte, so every accented word files after Z:
| « Émile » landed below « Zoé », at the bottom of a list read top to bottom.
| Collator applies the locale's rules instead.
|
| Only lists drawn from data are sorted. A status, an age category or a season
| is ordered on purpose, and stays as it is.
|
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

/** @return array{0: User, 1: User} accented name first alphabetically, last by bytes */
function accentedPair(): array
{
    return [
        User::factory()->create(['first_name' => 'Émile', 'last_name' => 'Zwart']),
        User::factory()->create(['first_name' => 'Zoé', 'last_name' => 'Aaron']),
    ];
}

it('orders the treasury member search on the displayed name', function (): void {
    [$emile, $zoe] = accentedPair();
    // Named so the treasurer cannot match the search term and join the list.
    $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)
        ->create(['first_name' => 'Bob', 'last_name' => 'Nemo']);

    $names = collect(
        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.payments')
            // « Zwart » and « Aaron » both hold « ar », so both match and the
            // order is the whole point. Under two characters the field returns
            // nothing at all.
            ->call('searchUsers', 'ar')
            ->get('usersSearchList')
    )->pluck('name');

    expect($names->all())->toBe(['Émile Zwart', 'Zoé Aaron'])
        ->and($emile->id)->not->toBe($zoe->id);
});

it('orders the people a member may pay for on the displayed name', function (): void {
    $parent = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Martin']);
    [$emile, $zoe] = accentedPair();

    $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
    $guardian->users()->attach([$emile->id, $zoe->id]);

    $names = Livewire::actingAs($parent)
        ->test('pages::club-admin.users.user-space.payments', ['user' => $parent])
        ->instance()
        ->payableUsers()
        ->pluck('full_name');

    expect($names->all())->toBe(['Alice Martin', 'Émile Zwart', 'Zoé Aaron']);
});

/*
 * « É » and « F » rather than A/B/C: ordinary letters sort identically either
 * way, so a test built on them passes whether the list is locale-aware or not.
 * É collates as E and belongs before F; compared byte by byte it lands after.
 * Team names are free text, so the case is reachable.
 */
it('orders the team filter of the members list, accents in their place', function (): void {
    $league = League::factory()->create(['season_id' => $this->season->id]);

    foreach (['F', 'É'] as $letter) {
        Team::factory()->create([
            'name' => $letter,
            'season_id' => $this->season->id,
            'league_id' => $league->id,
        ]);
    }

    $names = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.users.index')
        ->viewData('teams')
        ->pluck('name');

    expect($names->all())->toBe([__('Team') . ' É', __('Team') . ' F']);
});

/*
 * Two teams share the letter A across categories. Ordering on `name` alone left
 * that tie to the insertion order, so the same club showed « A · Vétérans »
 * above « A · Messieurs » or below it depending on nothing at all. The label the
 * dropdown builds is what decides.
 */
it('orders the directory team filter on the full label, breaking ties on the category', function (): void {
    $veterans = League::factory()->create(['season_id' => $this->season->id, 'category' => 'VETERANS']);
    $men = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    // Inserted in the order that used to decide the outcome.
    Team::factory()->create(['name' => 'A', 'season_id' => $this->season->id, 'league_id' => $veterans->id]);
    Team::factory()->create(['name' => 'A', 'season_id' => $this->season->id, 'league_id' => $men->id]);

    $viewer = activeMember($this->season);

    $names = Livewire::actingAs($viewer)
        ->test('pages::club-admin.users.user-space.directory', ['user' => $viewer])
        ->instance()
        ->teamsForFilter
        ->pluck('name');

    expect($names->first())->toBe('A · ' . LeagueCategory::MEN->label())
        ->and($names->last())->toBe('A · ' . LeagueCategory::VETERANS->label());
});

/*
 * Our own teams all share the club name, so the order comes down to the letter
 * that follows it — and « É » before « F » only holds under the locale's rules.
 */
it('orders our teams in the fixtures filter, accents in their place', function (): void {
    $club = Club::factory()->create(['is_own_club' => true, 'name' => 'CTT Ottignies-Blocry']);
    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    foreach (['F', 'É'] as $letter) {
        Team::factory()->create([
            'name' => $letter,
            'season_id' => $this->season->id,
            'league_id' => $league->id,
            'club_id' => $club->id,
        ]);
    }

    $names = collect(
        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.interclubs.interclubs')
            ->viewData('ourTeamOptions')
    )->pluck('name');

    expect($names->all())->toBe(['CTT Ottignies-Blocry É', 'CTT Ottignies-Blocry F']);
});
