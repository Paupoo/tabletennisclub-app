<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * La liste des candidats au noyau, quand le club est nombreux.
 *
 * Elle rendait d'un coup tous les compétiteurs éligibles, triés par
 * `orderBy(force_list)` — ce qui, en MySQL, remonte les `NULL` **en tête** : les
 * joueurs sans position dans la liste de force passaient devant ceux qui en ont
 * une, et la liste paraissait n'être triée par rien.
 *
 * Le compositeur d'équipes traitait déjà ce cas (`orderByRaw` sur la nullité) ;
 * cet écran ne l'avait jamais fait.
 */
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
    ]);
    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'name' => 'D',
        'captain_id' => null,
    ]);

    $this->admin = User::factory()->isAdmin()->create(['licence' => null]);
});

function eligible(string $lastName): User
{
    return User::factory()->isCompetitor()->create([
        'last_name' => $lastName,
        'ranking' => 'C6',
    ]);
}

/**
 * Poser les positions de liste de force, une fois tout le monde créé.
 *
 * Deux pièges se cumulent ici. `force_list` est casté mais **pas** `fillable` :
 * le passer à `create()` est ignoré en silence. Et créer un compétiteur change
 * son `ranking`, ce qui déclenche `RecalculateForceListAction` depuis
 * `UserObserver::saved()` — chaque création réécrit la liste de force de tous les
 * autres. Poser les positions au fil de l'eau les ferait écraser par la création
 * suivante, et le test mesurerait un ordre qu'il n'a pas choisi.
 *
 * @param  array<int, array{0: User, 1: int|null}>  $positions
 */
function placeOnForceList(array $positions): void
{
    foreach ($positions as [$user, $rank]) {
        $user->forceFill(['force_list' => $rank])->saveQuietly();
    }
}

it('ranks players by their force list position, the unplaced last', function (): void {
    $unplaced = eligible('Aaa');
    $third = eligible('Zzz');
    $first = eligible('Mmm');

    placeOnForceList([[$unplaced, null], [$third, 3], [$first, 1]]);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team]);

    $order = $component->viewData('competitors')->pluck('id')->all();

    expect($order)->toBe([$first->id, $third->id, $unplaced->id]);
});

it('paginates instead of rendering the whole club', function (): void {
    User::factory()->isCompetitor()->count(30)->create(['ranking' => 'C6']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team]);

    $competitors = $component->viewData('competitors');

    expect($competitors)->toBeInstanceOf(Paginator::class)
        ->and($competitors->count())->toBeLessThan(30)
        ->and($competitors->total())->toBe(30);
});

it('keeps a player selected on page one while browsing page two', function (): void {
    $players = User::factory()->isCompetitor()->count(30)->create(['ranking' => 'C6']);
    $picked = $players->first();

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->call('toggleMember', $picked->id)
        ->set('paginators.page', 2);

    expect($component->get('memberIds'))->toContain($picked->id);
});

it('goes back to the first page when the search changes', function (): void {
    User::factory()->isCompetitor()->count(30)->create(['ranking' => 'C6']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('paginators.page', 2)
        ->set('memberSearch', 'Dupont');

    expect($component->get('paginators')['page'])->toBe(1);
});

it('renders the pager, not just the paginated query', function (): void {
    User::factory()->isCompetitor()->count(30)->create(['ranking' => 'C6']);

    // Viser le marqueur du paginateur Livewire : un simple « Suivant » se trouve
    // ailleurs dans la page et l'assertion passerait sans aucun paginateur.
    $html = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->html();

    expect($html)->toContain('wire:click="nextPage');
});

it('still shows who is in the core while browsing another page', function (): void {
    $players = User::factory()->isCompetitor()->count(30)->create(['ranking' => 'C6']);

    // Première de la liste de force : elle est donc en page 1, et sûrement pas
    // dans la page 2 qu'on consulte. Sans bloc « noyau », son nom disparaîtrait.
    $picked = $players->first();
    placeOnForceList([[$picked, 1]]);
    $this->team->users()->attach($picked->id);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('paginators.page', 2);

    expect($component->viewData('competitors')->pluck('id')->all())
        ->not->toContain($picked->id);

    // Le nom se trouve aussi dans les options du sélecteur de capitaine : un
    // assertSee() nu passerait sans le moindre bloc « noyau ». On découpe donc
    // le HTML autour du bloc visé avant d'y chercher le nom.
    $html = $component->html();
    $heading = e(__('Current core'));

    expect($html)->toContain($heading);

    $block = substr($html, strpos($html, $heading), 3000);

    expect($block)->toContain(e($picked->last_name));
});
