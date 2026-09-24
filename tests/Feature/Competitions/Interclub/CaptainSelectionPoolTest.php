<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Le tiroir de composition, vu par le capitaine qui a besoin d'un joueur.
 *
 * Deux équipes de la même catégorie jouent la même journée. A publie sa compo et
 * laisse quelqu'un de côté ; B, à qui il manque du monde, doit le voir, pouvoir
 * l'appeler et le cocher — le reste est le flux existant.
 */
beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->ownClub = Club::factory()->ownClub()->create();
    $this->forceQueue = [];
});

/**
 * @param  array<int, int|null>  $forceIndices
 * @return array{0: Team, 1: Interclub, 2: array<int, User>}
 */
function poolSquad(string $name, array $forceIndices, ?User $captain = null): array
{
    $team = Team::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'club_id' => test()->ownClub->id,
        'captain_id' => $captain?->id,
        'name' => $name,
    ]);

    $players = [];

    foreach ($forceIndices as $index) {
        $player = User::factory()->isCompetitor()->create([
            'phone_number' => '0470 00 00 0' . count(poolForceQueue()),
        ]);
        poolForceQueue([$player, $index]);
        $players[] = $player;
    }

    if ($captain instanceof User) {
        $team->users()->attach($captain->id);
    }

    $team->users()->attach(collect($players)->pluck('id')->all());

    $fixture = Interclub::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'visited_team_id' => $team->id,
        'week_number' => 42,
        'total_players' => 4,
        'is_bye' => false,
        'start_date_time' => now()->addDays(7),
    ]);

    return [$team, $fixture, $players];
}

/**
 * @param  array{0: User, 1: int|null}|null  $entry
 * @return array<int, array{0: User, 1: int|null}>
 */
function poolForceQueue(?array $entry = null, bool $reset = false): array
{
    static $queue = [];

    if ($reset) {
        return $queue = [];
    }

    if ($entry !== null) {
        $queue[] = $entry;
    }

    return $queue;
}

/** Les positions de liste de force se posent une fois tout le monde créé. */
function settlePoolForce(): void
{
    foreach (poolForceQueue() as [$player, $index]) {
        $player->forceFill(['force_list' => $index])->saveQuietly();
    }
}

function publishFor(Interclub $fixture, array $selected): void
{
    foreach ($selected as $player) {
        $fixture->select($player);
        $fixture->users()->updateExistingPivot($player->id, ['selection_confirmed_at' => now()]);
    }
}

function openDrawerAs(User $captain, Interclub $fixture)
{
    return Livewire::actingAs($captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $fixture->id);
}

it('shows a captain the players their sibling team left free', function (): void {
    poolForceQueue(reset: true);

    $captainB = User::factory()->isCompetitor()->create();
    [, $fixtureA, $playersA] = poolSquad('A', [1, 2, 3, 4, 20]);
    [, $fixtureB] = poolSquad('B', [30, 31], $captainB);

    settlePoolForce();

    $leftOut = $playersA[4];
    $fixtureA->markAvailability($leftOut, InterclubAvailability::AVAILABLE, 'pas avant 20h');
    publishFor($fixtureA, array_slice($playersA, 0, 4));

    $rows = openDrawerAs($captainB, $fixtureB)->viewData('poolRows');

    expect($rows->pluck('id')->all())->toBe([$leftOut->id])
        ->and($rows->first()['origin_team'])->toBe('A')
        ->and($rows->first()['availability_note'])->toBe('pas avant 20h')
        // Décision 10 : l'opt-in de visibilité est outrepassé, le capitaine appelle.
        ->and($rows->first()['phone_number'])->not->toBeNull();
});

it('lets the captain tick a free player into the lineup', function (): void {
    poolForceQueue(reset: true);

    $captainB = User::factory()->isCompetitor()->create();
    [, $fixtureA, $playersA] = poolSquad('A', [1, 2, 3, 4, 20]);
    [, $fixtureB] = poolSquad('B', [30, 31], $captainB);

    settlePoolForce();

    $leftOut = $playersA[4];
    $fixtureA->markAvailability($leftOut, InterclubAvailability::AVAILABLE);
    publishFor($fixtureA, array_slice($playersA, 0, 4));

    $component = openDrawerAs($captainB, $fixtureB)->call('togglePlayer', $leftOut->id);

    expect($component->get('selectedPlayerIds'))->toContain($leftOut->id);

    // Et une fois coché, il quitte le pool : on ne le propose pas deux fois.
    expect($component->viewData('poolRows')->pluck('id')->all())->not->toContain($leftOut->id);
});

/**
 * Article C.22 : le premier joueur de B ne peut être plus fort que le troisième
 * aligné en A. On cesse de proposer plutôt que de refuser le geste ensuite —
 * mais on dit combien de joueurs ont disparu, sinon la liste semble vide.
 */
it('hides a free player rule C.22 forbids, and says how many', function (): void {
    poolForceQueue(reset: true);

    $captainB = User::factory()->isCompetitor()->create();
    [, $fixtureA, $playersA] = poolSquad('A', [1, 2, 10, 11, 3]);
    [, $fixtureB] = poolSquad('B', [30, 31], $captainB);

    settlePoolForce();

    // A aligne #1, #2, #10, #11 → son troisième est #10.
    $tooStrong = $playersA[4]; // #3, plus fort que le seuil
    $fixtureA->markAvailability($tooStrong, InterclubAvailability::AVAILABLE);
    publishFor($fixtureA, array_slice($playersA, 0, 4));

    $component = openDrawerAs($captainB, $fixtureB);

    expect($component->viewData('poolRows'))->toBeEmpty()
        ->and($component->viewData('poolHiddenCount'))->toBe(1);
});

it('refuses to tick a player rule C.22 forbids, even if asked directly', function (): void {
    poolForceQueue(reset: true);

    $captainB = User::factory()->isCompetitor()->create();
    [, $fixtureA, $playersA] = poolSquad('A', [1, 2, 10, 11, 3]);
    [, $fixtureB] = poolSquad('B', [30, 31], $captainB);

    settlePoolForce();

    $tooStrong = $playersA[4];
    $fixtureA->markAvailability($tooStrong, InterclubAvailability::AVAILABLE);
    publishFor($fixtureA, array_slice($playersA, 0, 4));

    $component = openDrawerAs($captainB, $fixtureB)->call('togglePlayer', $tooStrong->id);

    expect($component->get('selectedPlayerIds'))->not->toContain($tooStrong->id);
});

/**
 * Décision 20 : un joueur de son propre effectif que la règle interdit reste
 * visible, case grisée, motif en clair. Le faire disparaître passerait pour un
 * bug plutôt que pour une règle.
 */
it('greys out an own player rule C.22 forbids instead of hiding them', function (): void {
    poolForceQueue(reset: true);

    $captainB = User::factory()->isCompetitor()->create();
    [, $fixtureA, $playersA] = poolSquad('A', [10, 11, 12, 13]);
    [, $fixtureB, $playersB] = poolSquad('B', [2, 30], $captainB);

    settlePoolForce();

    publishFor($fixtureA, $playersA);

    $rows = openDrawerAs($captainB, $fixtureB)->viewData('roster');
    $tooStrong = $rows->firstWhere('id', $playersB[0]->id);

    expect($tooStrong)->not->toBeNull()
        ->and($tooStrong['is_illegal'])->toBeTrue()
        ->and($tooStrong['legality_reason'])->not->toBeNull();
});

it('names the sibling captain still holding players back', function (): void {
    poolForceQueue(reset: true);

    $captainA = User::factory()->isCompetitor()->create(['phone_number' => '0470 11 22 33']);
    $captainB = User::factory()->isCompetitor()->create();

    [, $fixtureA, $playersA] = poolSquad('A', [1, 2, 3, 4], $captainA);
    [, $fixtureB] = poolSquad('B', [30, 31], $captainB);

    settlePoolForce();

    $fixtureA->markAvailability($playersA[0], InterclubAvailability::AVAILABLE);
    // A n'a rien publié.

    $waiting = openDrawerAs($captainB, $fixtureB)->viewData('poolWaiting');

    expect($waiting)->toHaveCount(1)
        ->and($waiting->first()->team->name)->toBe('A')
        ->and($waiting->first()->availableCount)->toBe(1)
        ->and($waiting->first()->team->captain->phone_number)->toBe('0470 11 22 33');
});

/**
 * Sans indice de force, pas d'alignement : ce n'est pas une incertitude C.22 à
 * lever par un coup de fil, c'est une case fermée.
 */
describe('a player without a force index', function (): void {
    it('greys out an own player and refuses the tick', function (): void {
        poolForceQueue(reset: true);

        $captain = User::factory()->isCompetitor()->create();
        [, $fixture, $players] = poolSquad('A', [5, null], $captain);

        settlePoolForce();

        $component = openDrawerAs($captain, $fixture);
        $row = $component->viewData('roster')->firstWhere('id', $players[1]->id);

        expect($row['is_illegal'])->toBeTrue()
            ->and($row['legality_reason'])->toBe(__('This player has no force index: they cannot be lined up.'));

        $component->call('togglePlayer', $players[1]->id);

        expect($component->get('selectedPlayerIds'))->not->toContain($players[1]->id);

        // Un joueur indexé de la même équipe reste sélectionnable.
        $component->call('togglePlayer', $players[0]->id);

        expect($component->get('selectedPlayerIds'))->toContain($players[0]->id);
    });

    it('hides a free player, counted apart from those rule C.22 forbids', function (): void {
        poolForceQueue(reset: true);

        $captainB = User::factory()->isCompetitor()->create();
        [, $fixtureA, $playersA] = poolSquad('A', [1, 2, 3, 4, null]);
        [, $fixtureB] = poolSquad('B', [30, 31], $captainB);

        settlePoolForce();

        $fixtureA->markAvailability($playersA[4], InterclubAvailability::AVAILABLE);
        publishFor($fixtureA, array_slice($playersA, 0, 4));

        $component = openDrawerAs($captainB, $fixtureB);

        expect($component->viewData('poolRows'))->toBeEmpty()
            ->and($component->viewData('poolUnrankedCount'))->toBe(1)
            ->and($component->viewData('poolHiddenCount'))->toBe(0);

        $component->assertSee(trans_choice('{1} :count player hidden: no force index.|[2,*] :count players hidden: no force index.', 1, ['count' => 1]));
    });

    it('still lets the captain untick one lined up before the rule', function (): void {
        poolForceQueue(reset: true);

        $captain = User::factory()->isCompetitor()->create();
        [, $fixture, $players] = poolSquad('A', [5, null], $captain);

        settlePoolForce();

        $fixture->select($players[1]);

        $component = openDrawerAs($captain, $fixture);

        expect($component->get('selectedPlayerIds'))->toContain($players[1]->id);

        $component->call('togglePlayer', $players[1]->id);

        expect($component->get('selectedPlayerIds'))->not->toContain($players[1]->id);
    });
});
