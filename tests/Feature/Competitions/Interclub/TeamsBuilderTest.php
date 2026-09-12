<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Ranking;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    Club::factory()->ownClub()->create();

    $this->admin = User::factory()->isAdmin()->isNotCompetitor()->create();
});

/**
 * Compétiteur éligible aux interclubs : licence compétitive confirmée sur la
 * saison en cours et classement réel (NA est hors périmètre).
 */
function eligibleCompetitor(Ranking $ranking, string $lastName): User
{
    return User::factory()
        ->isCompetitor()
        ->setRanking($ranking)
        ->create(['last_name' => $lastName, 'first_name' => 'Test']);
}

/**
 * Régression : `ranking` est casté en enum depuis ca2a9b47, et le tri par
 * classement passait l'enum à strcmp() — le compositeur plantait dès l'étape 2.
 */
test('computing the distribution sorts players by ranking', function (): void {
    $rankings = [Ranking::B0, Ranking::C4, Ranking::D2, Ranking::E0, Ranking::E6, Ranking::NC];

    $players = collect($rankings)
        ->map(fn (Ranking $ranking, int $i): User => eligibleCompetitor($ranking, 'Player' . $i));

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('nucleusSize', 6)
        ->call('computeDistribution')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('proposedTeams.0.letter', 'A')
        ->assertSet('proposedTeams.0.players', $players->pluck('id')->all());
});

test('the waiting modal closes when there are not enough competitors', function (): void {
    eligibleCompetitor(Ranking::C4, 'Alone');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('nucleusSize', 6)
        ->call('startComputing')
        ->assertSet('showComputingModal', true)
        ->call('computeDistribution')
        ->assertSet('showComputingModal', false)
        ->assertSet('step', 1)
        ->assertSet('proposedTeams', []);
});

test('the waiting modal closes once the distribution is computed', function (): void {
    collect([Ranking::C4, Ranking::D2, Ranking::E0, Ranking::E6, Ranking::NC, Ranking::NC])
        ->each(fn (Ranking $ranking, int $i): User => eligibleCompetitor($ranking, 'Eee' . $i));

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('nucleusSize', 6)
        ->call('startComputing')
        ->call('computeDistribution')
        ->assertSet('showComputingModal', false)
        ->assertSet('step', 2);
});

test('moving a player back into a team re-sorts it by ranking', function (): void {
    $strongest = eligibleCompetitor(Ranking::B0, 'Aaa');
    $others = collect([Ranking::C4, Ranking::D2, Ranking::E0, Ranking::E6, Ranking::NC])
        ->map(fn (Ranking $ranking, int $i): User => eligibleCompetitor($ranking, 'Bbb' . $i));

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('nucleusSize', 6)
        ->call('computeDistribution')
        ->call('movePlayerToUnassigned', $strongest->id)
        ->assertSet('unassigned', [$strongest->id])
        ->call('movePlayerToTeam', $strongest->id, 0);

    // Ajouté en fin de tableau par movePlayerToTeam, puis remonté en tête : le
    // B0 précède les autres classements.
    $component
        ->assertSet('unassigned', [])
        ->assertSet('proposedTeams.0.players', [$strongest->id, ...$others->pluck('id')->all()]);
});

test('the captain of a team loses the title when moved out of it', function (): void {
    $players = collect([Ranking::C4, Ranking::D2, Ranking::E0, Ranking::E6, Ranking::NC, Ranking::NC])
        ->map(fn (Ranking $ranking, int $i): User => eligibleCompetitor($ranking, 'Ccc' . $i));

    $captain = $players->first();

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('nucleusSize', 6)
        ->call('computeDistribution')
        ->call('setCaptainInTeam', 0, $captain->id)
        ->assertSet('proposedTeams.0.captainId', $captain->id)
        ->call('movePlayerToUnassigned', $captain->id)
        ->assertSet('proposedTeams.0.captainId', null);
});

test('admin saves the proposed composition as teams', function (): void {
    $players = collect([Ranking::C4, Ranking::D2, Ranking::E0, Ranking::E6, Ranking::NC, Ranking::NC])
        ->map(fn (Ranking $ranking, int $i): User => eligibleCompetitor($ranking, 'Ddd' . $i));

    $captain = $players->first();

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('nucleusSize', 6)
        ->call('computeDistribution')
        ->call('setCaptainInTeam', 0, $captain->id)
        ->set('proposedTeams.0.level', 'PROVINCIAL_BW')
        ->set('proposedTeams.0.division', '3B')
        ->call('save')
        ->assertHasNoErrors();

    $team = Team::where('name', 'A')->sole();

    expect($team->season_id)->toBe($this->season->id);
    expect($team->captain_id)->toBe($captain->id);
    expect($team->users()->pluck('users.id')->sort()->values()->all())
        ->toBe($players->pluck('id')->sort()->values()->all());

    $league = League::sole();
    expect([$league->category, $league->level, $league->division])
        ->toBe(['MEN', 'PROVINCIAL_BW', '3B']);
});

test('a member cannot open the teams builder', function (): void {
    $member = User::factory()->isNotCompetitor()->create();

    $this->actingAs($member)
        ->get(route('admin.interclubs.teams.builder'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Une saison ne se compose qu'une fois par catégorie
|--------------------------------------------------------------------------
|
| Le compositeur découpe la liste de force entière en tranches et renomme à
| partir de « A ». Le relancer sur une catégorie déjà composée ne produit pas un
| complément : il produit une seconde équipe A, une seconde B, et réinscrit tout
| le monde. C'était l'autre porte par laquelle un joueur se retrouvait dans deux
| noyaux, sans que personne n'ait touché à l'écran d'édition.
|
| Le refus tombe à l'étape 1 : inutile de faire calculer une répartition et
| déplacer des joueurs pendant dix minutes pour la jeter ensuite.
*/

test('the builder refuses a category the season already has teams in', function (): void {
    $league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
    ]);
    Team::create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => Club::own()?->id,
    ]);

    collect(range(1, 6))->each(fn (int $i) => eligibleCompetitor(Ranking::D2, 'Joueur' . $i));

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('teamCategory', 'MEN')
        ->set('nucleusSize', 6)
        ->call('startComputing')
        ->assertHasErrors('teamCategory')
        ->assertSet('step', 1);

    expect(Team::count())->toBe(1);
});

test('the builder still opens for a category nobody has composed yet', function (): void {
    $league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
    ]);
    Team::create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => Club::own()?->id,
    ]);

    collect(range(1, 6))->each(fn (int $i) => eligibleCompetitor(Ranking::D2, 'Joueuse' . $i));

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('teamCategory', 'VETERANS')
        ->set('nucleusSize', 6)
        ->call('startComputing')
        ->assertHasNoErrors();
});

test('the refusal is rendered, not just raised', function (): void {
    $league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
    ]);
    Team::create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => Club::own()?->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.builder')
        ->set('teamCategory', 'MEN')
        ->call('startComputing')
        ->assertSee(__('This season already has :count team(s) in this category. Edit them from the teams list instead.', ['count' => 1]));
});
