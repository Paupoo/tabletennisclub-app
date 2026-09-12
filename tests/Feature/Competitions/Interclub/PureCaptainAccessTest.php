<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Le capitaine « pur » : aucune délégation, aucune affiliation, aucun noyau.
 *
 * C'est le profil que le club veut pouvoir nommer — un bénévole qui donne son
 * temps. Élargir le vivier ne doit surtout pas élargir l'accès : il compose et
 * encode **pour ses équipes**, et se fait refuser partout ailleurs.
 *
 * Testé contre les deux écrans, pas contre les Gates : un Gate vert ne prouve
 * pas qu'un écran rétrécit.
 */
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->ourClub = Club::factory()->ownClub()->create();
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->hisTeam = Team::factory()->create([
        'season_id' => $this->season->id, 'league_id' => $this->league->id,
        'club_id' => $this->ourClub->id, 'name' => 'D', 'captain_id' => null,
    ]);
    $this->otherTeam = Team::factory()->create([
        'season_id' => $this->season->id, 'league_id' => $this->league->id,
        'club_id' => $this->ourClub->id, 'name' => 'E', 'captain_id' => null,
    ]);

    // Un bénévole : pas de licence, pas d'affiliation, pas de rôle, aucun noyau.
    $this->volunteer = User::factory()->isNotCompetitor()->create([
        'last_name' => 'Bénévole',
        'email_verified_at' => now(),
    ]);
    $this->hisTeam->update(['captain_id' => $this->volunteer->id]);

    $this->opponent = Club::factory()->create(['is_own_club' => false]);
});

function fixtureFor(Team $team, int $week = 3): Interclub
{
    $away = Team::factory()->create([
        'season_id' => test()->season->id, 'league_id' => test()->league->id,
        'club_id' => test()->opponent->id, 'name' => 'Z', 'captain_id' => null,
    ]);

    return Interclub::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'visited_team_id' => $team->id,
        'visiting_team_id' => $away->id,
        'week_number' => $week,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);
}

it('holds no permission of its own', function (): void {
    expect($this->volunteer->can('selections.manage'))->toBeFalse()
        ->and($this->volunteer->can('results.manage'))->toBeFalse()
        ->and($this->volunteer->can('interclubs.manage'))->toBeFalse()
        ->and($this->volunteer->teams()->count())->toBe(0);
});

it('reaches the selection screen for the team it captains', function (): void {
    $fixture = fixtureFor($this->hisTeam);

    Livewire::actingAs($this->volunteer)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $fixture->id)
        ->assertOk();
});

it('is refused the selection of a team it does not captain', function (): void {
    $fixture = fixtureFor($this->otherTeam, week: 4);

    Livewire::actingAs($this->volunteer)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $fixture->id)
        ->assertForbidden();
});

it('records a result for its own team', function (): void {
    Livewire::actingAs($this->volunteer)
        ->test('pages::club-events.interclubs.results')
        ->call('updateFinalPosition', $this->hisTeam->id, '3')
        ->assertOk();

    expect($this->hisTeam->fresh()->final_position)->toBe('3');
});

it('is refused a result for a team it does not captain', function (): void {
    Livewire::actingAs($this->volunteer)
        ->test('pages::club-events.interclubs.results')
        ->call('updateFinalPosition', $this->otherTeam->id, '1')
        ->assertForbidden();

    expect($this->otherTeam->fresh()->final_position)->toBeNull();
});

it('only ever sees its own teams listed on the results screen', function (): void {
    $component = Livewire::actingAs($this->volunteer)
        ->test('pages::club-events.interclubs.results');

    // L'écran libelle « Équipe X » : viser ce libellé, pas une lettre nue qui se
    // trouverait n'importe où dans la page.
    $component->assertSee(__('Team') . ' ' . $this->hisTeam->name)
        ->assertDontSee(__('Team') . ' ' . $this->otherTeam->name);
});

it('loses that access the day it stops captaining', function (): void {
    $this->hisTeam->update(['captain_id' => null]);

    expect(Gate::forUser($this->volunteer->fresh())->allows('access-selections'))->toBeFalse()
        ->and(Gate::forUser($this->volunteer->fresh())->allows('access-results'))->toBeFalse();
});
