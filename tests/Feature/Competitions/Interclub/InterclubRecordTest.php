<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->player = User::factory()->isCompetitor()->create();
    $this->ourClub = Club::factory()->create(['is_own_club' => true]);
    $this->theirClub = Club::factory()->create(['is_own_club' => false]);
});

/**
 * A played tie in the named season, with our club at home.
 */
function tieIn(string $seasonName, string $playedOn): Interclub
{
    $season = Season::factory()->create(['name' => $seasonName, 'is_active' => false]);
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);

    $ours = Team::factory()->create([
        'club_id' => test()->ourClub->id, 'league_id' => $league->id, 'season_id' => $season->id,
    ]);
    $theirs = Team::factory()->create([
        'club_id' => test()->theirClub->id, 'league_id' => $league->id, 'season_id' => $season->id,
    ]);

    return Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $ours->id,
        'visiting_team_id' => $theirs->id,
        'start_date_time' => $playedOn,
    ]);
}

function record(?User $as = null, array $params = []): Testable
{
    $user = $as ?? test()->player;

    return Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.interclub-record', ['user' => $user, ...$params]);
}

it('shows an empty record to a member who has never been on a sheet', function (): void {
    record()->assertSee(__('No interclub match recorded yet'));
});

it('counts only the lines the member is named on', function (): void {
    $tie = tieIn('2024-2025', now()->subYear()->toDateTimeString());
    $other = User::factory()->create();

    foreach ([1, 2, 3] as $position) {
        InterclubIndividualMatch::factory()->create([
            'interclub_id' => $tie->id, 'position' => $position,
            'user_id' => $this->player->id, 'we_won' => $position !== 3,
        ]);
    }

    // Another member's lines, and the double nobody owns, must not be counted.
    InterclubIndividualMatch::factory()->create([
        'interclub_id' => $tie->id, 'position' => 4, 'user_id' => $other->id, 'we_won' => true,
    ]);
    InterclubIndividualMatch::factory()->double()->create([
        'interclub_id' => $tie->id, 'we_won' => true,
    ]);

    $totals = record()->viewData('totals');

    expect($totals['played'])->toBe(3)
        ->and($totals['won'])->toBe(2)
        ->and($totals['rate'])->toBe(67)
        ->and($totals['ties'])->toBe(1);
});

it('groups ties by season, newest first', function (): void {
    $recent = tieIn('2025-2026', now()->subMonths(2)->toDateTimeString());
    $old = tieIn('2019-2020', now()->subYears(6)->toDateTimeString());

    foreach ([$recent, $old] as $tie) {
        InterclubIndividualMatch::factory()->create([
            'interclub_id' => $tie->id, 'position' => 1,
            'user_id' => $this->player->id, 'we_won' => true,
        ]);
    }

    $bySeason = record()->viewData('bySeason');

    expect($bySeason->keys()->all())->toBe(['2025-2026', '2019-2020']);
});

it('offers only the seasons the member actually played', function (): void {
    $tie = tieIn('2024-2025', now()->subYear()->toDateTimeString());
    Season::factory()->create(['name' => '2018-2019', 'is_active' => false]);

    InterclubIndividualMatch::factory()->create([
        'interclub_id' => $tie->id, 'position' => 1,
        'user_id' => $this->player->id, 'we_won' => true,
    ]);

    $seasons = record()->viewData('seasons');

    expect($seasons->pluck('name')->all())->toBe(['2024-2025']);
});

it('narrows to one season when asked', function (): void {
    $recent = tieIn('2025-2026', now()->subMonths(2)->toDateTimeString());
    $old = tieIn('2019-2020', now()->subYears(6)->toDateTimeString());

    foreach ([$recent, $old] as $tie) {
        InterclubIndividualMatch::factory()->create([
            'interclub_id' => $tie->id, 'position' => 1,
            'user_id' => $this->player->id, 'we_won' => true,
        ]);
    }

    $component = record()->set('seasonId', $recent->season_id);

    expect($component->viewData('totals')['played'])->toBe(1)
        ->and($component->viewData('bySeason')->keys()->all())->toBe(['2025-2026']);
});

it('keeps a member out of somebody else record', function (): void {
    $other = User::factory()->create();

    Livewire::actingAs($this->player)
        ->test('pages::club-admin.users.user-space.interclub-record', ['user' => $other])
        ->assertForbidden();
});

it('reaches a match played for a team whose roster is empty', function (): void {
    // An imported season: the federation publishes its teams, never our roster.
    $tie = tieIn('2016-2017', now()->subYears(9)->toDateTimeString());

    InterclubIndividualMatch::factory()->create([
        'interclub_id' => $tie->id, 'position' => 1,
        'user_id' => $this->player->id, 'we_won' => true,
    ]);

    expect($this->player->teams()->count())->toBe(0);

    record()->assertOk()->assertSee('2016-2017');
});

it('keeps the profile card off a member who has never played', function (): void {
    Livewire::actingAs($this->player)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $this->player])
        ->assertOk()
        ->assertDontSee(__('My interclub record'));
});

it('puts the record on the profile, most recent form last', function (): void {
    $tie = tieIn('2024-2025', now()->subYear()->toDateTimeString());

    // Lost the first, won the next three.
    foreach ([1, 2, 3, 4] as $position) {
        InterclubIndividualMatch::factory()->create([
            'interclub_id' => $tie->id, 'position' => $position,
            'user_id' => $this->player->id, 'we_won' => $position !== 1,
        ]);
    }

    $component = Livewire::actingAs($this->player)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $this->player]);

    $component->assertOk()->assertSee(__('My interclub record'));

    $record = $component->instance()->interclubRecord();

    expect($record['played'])->toBe(4)
        ->and($record['won'])->toBe(3)
        ->and($record['rate'])->toBe(75)
        ->and($record['recent'])->toHaveCount(4);
});
