<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\OfficialTournamentMatch;
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

/**
 * A tournament match of the player's, in the named season.
 */
function tournamentMatchIn(Season $season, array $attributes = []): OfficialTournamentMatch
{
    return OfficialTournamentMatch::factory()->playedBy(test()->player)->create([
        'season_id' => $season->id,
        ...$attributes,
    ]);
}

function record(?User $as = null, array $params = []): Testable
{
    $user = $as ?? test()->player;

    return Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.results', ['user' => $user, ...$params]);
}

it('shows an empty record to a member who has never played', function (): void {
    record()->assertSee(__('No match recorded yet'));
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

    $totals = record()->viewData('totals')['interclub'];

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

    expect($component->viewData('totals')['interclub']['played'])->toBe(1)
        ->and($component->viewData('bySeason')->keys()->all())->toBe(['2025-2026']);
});

it('keeps a member out of somebody else record', function (): void {
    $other = User::factory()->create();

    Livewire::actingAs($this->player)
        ->test('pages::club-admin.users.user-space.results', ['user' => $other])
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
        ->assertDontSee(__('My results'));
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

    $component->assertOk()->assertSee(__('My results'));

    $record = $component->instance()->results();

    expect($record['totals']['all']['played'])->toBe(4)
        ->and($record['totals']['all']['won'])->toBe(3)
        ->and($record['totals']['all']['rate'])->toBe(75)
        ->and($record['recent'])->toHaveCount(4);
});

it('lives at /results, and the old address still finds it', function (): void {
    $this->actingAs($this->player)
        ->get('/admin/my-space/' . $this->player->id . '/results')
        ->assertOk();

    $this->actingAs($this->player)
        ->get('/admin/my-space/' . $this->player->id . '/interclub-record')
        ->assertRedirect(route('admin.user.results', $this->player));
});

it('opens on everything, the combined figure always beside its split', function (): void {
    $tie = tieIn('2025-2026', now()->subMonths(2)->toDateTimeString());

    foreach ([1, 2] as $position) {
        InterclubIndividualMatch::factory()->create([
            'interclub_id' => $tie->id, 'position' => $position,
            'user_id' => $this->player->id, 'we_won' => true,
        ]);
    }

    tournamentMatchIn($tie->season, ['we_won' => false]);
    tournamentMatchIn($tie->season, ['we_won' => true]);

    $component = record();
    $totals = $component->viewData('totals');

    expect($component->get('tab'))->toBe('all')
        ->and($totals['all'])->toBe(['played' => 4, 'rate' => 75, 'won' => 3])
        ->and($totals['interclub']['played'])->toBe(2)
        ->and($totals['interclub']['rate'])->toBe(100)
        ->and($totals['tournaments']['played'])->toBe(2)
        ->and($totals['tournaments']['rate'])->toBe(50);
});

it('never lets a tournament into the interclub figures', function (): void {
    $tie = tieIn('2025-2026', now()->subMonths(2)->toDateTimeString());

    InterclubIndividualMatch::factory()->create([
        'interclub_id' => $tie->id, 'position' => 1,
        'user_id' => $this->player->id, 'we_won' => false,
    ]);
    tournamentMatchIn($tie->season, ['we_won' => true]);

    $component = record();

    expect($component->viewData('totals')['interclub'])->toMatchArray(['played' => 1, 'won' => 0, 'rate' => 0, 'ties' => 1])
        ->and($component->viewData('bySeason')->flatten(1))->toHaveCount(1);
});

it('groups tournament matches by season, then tournament, then serie', function (): void {
    $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => false]);

    tournamentMatchIn($season, ['played_on' => '2025-10-12', 'tournament_name' => 'Critérium B - Nivelles', 'serie_name' => 'Série D']);
    tournamentMatchIn($season, ['played_on' => '2025-10-12', 'tournament_name' => 'Critérium B - Nivelles', 'serie_name' => 'Série D']);
    tournamentMatchIn($season, ['played_on' => '2025-10-12', 'tournament_name' => 'Critérium B - Nivelles', 'serie_name' => 'Série C']);
    tournamentMatchIn($season, ['played_on' => '2026-02-01', 'tournament_name' => 'Tournoi de Frameries', 'serie_name' => 'Série D']);

    $component = record();
    $tournaments = $component->viewData('tournamentsBySeason')['2025-2026'];

    // Newest tournament first.
    expect($tournaments->pluck('name')->all())->toBe(['Tournoi de Frameries', 'Critérium B - Nivelles'])
        ->and($tournaments[1]['series']->keys()->all())->toEqualCanonicalizing(['Série D', 'Série C'])
        ->and($tournaments[1]['series']['Série D'])->toHaveCount(2)
        ->and($component->viewData('totals')['tournaments']['tournaments'])->toBe(2);
});

it('lists every match of both kinds newest first on the everything tab', function (): void {
    $tie = tieIn('2025-2026', '2025-11-08 19:45:00');

    InterclubIndividualMatch::factory()->create([
        'interclub_id' => $tie->id, 'position' => 1,
        'user_id' => $this->player->id, 'we_won' => true,
    ]);
    tournamentMatchIn($tie->season, ['played_on' => '2025-12-14', 'tournament_name' => 'Critérium B - Nivelles']);
    tournamentMatchIn($tie->season, ['played_on' => '2025-10-05', 'tournament_name' => 'Tournoi de Frameries']);

    $feed = record()->viewData('feed');

    expect($feed->pluck('kind')->all())->toBe(['tournament', 'interclub', 'tournament'])
        ->and($feed->first()['context'])->toContain('Critérium B - Nivelles');
});

it('offers a season the member only played tournaments in', function (): void {
    $season = Season::factory()->create(['name' => '2023-2024', 'is_active' => false]);
    tournamentMatchIn($season);

    expect(record()->viewData('seasons')->pluck('name')->all())->toBe(['2023-2024']);
});

it('narrows the tournaments to one season too', function (): void {
    $recent = Season::factory()->create(['name' => '2025-2026', 'is_active' => false]);
    $old = Season::factory()->create(['name' => '2022-2023', 'is_active' => false]);
    tournamentMatchIn($recent);
    tournamentMatchIn($old);

    $component = record()->set('seasonId', $recent->id);

    expect($component->viewData('totals')['tournaments']['played'])->toBe(1)
        ->and($component->viewData('tournamentsBySeason')->keys()->all())->toBe(['2025-2026']);
});

it('puts tournaments on the profile card, split from interclub', function (): void {
    $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => false]);
    tournamentMatchIn($season, ['we_won' => true, 'played_on' => now()->subDay()]);

    $component = Livewire::actingAs($this->player)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $this->player]);

    $record = $component->instance()->results();

    $component->assertSee(__('My results'));

    expect($record['totals']['tournaments']['played'])->toBe(1)
        ->and($record['totals']['interclub']['played'])->toBe(0)
        ->and($record['recent'])->toHaveCount(1)
        ->and($record['recent'][0]['kind'])->toBe('tournament');
});

it('puts My results in the menu of a member who only plays tournaments', function (): void {
    $member = User::factory()->isNotCompetitor()->create();
    OfficialTournamentMatch::factory()->playedBy($member)->create();
    $this->actingAs($member);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $member]);

    expect($html)->toContain(route('admin.user.results', $member))
        // Upcoming interclub ties stay for those who play interclub.
        ->and($html)->not->toContain(route('admin.interclubs.my-matches'));
});

it('keeps My results out of the menu of a member with nothing to show', function (): void {
    $member = User::factory()->isNotCompetitor()->create();
    $this->actingAs($member);

    $html = (string) $this->blade('<x-admin.navigation :user="$user" />', ['user' => $member]);

    expect($html)->not->toContain(route('admin.user.results', $member));
});
