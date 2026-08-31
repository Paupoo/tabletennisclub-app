<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

beforeEach(function (): void {
    $season = Season::factory()->create();

    $league = League::create([
        'division' => '1A',
        'level' => 'NATIONAL',
        'category' => 'VETERANS',
        'season_id' => $season->id,
    ]);

    $this->member = User::factory()->create([
        'licence' => null,
    ]);

    $this->committee_member = User::factory()->isCommitteeMember()->withRole(Role::INTERCLUBS)->create([
        'licence' => null,
    ]);

    $this->admin = User::factory()->isAdmin()->create([
        'licence' => null,
    ]);

    User::factory()->count(4)->create();

    $this->team = Team::create([
        'name' => 'Z',
        'season_id' => $season->id,
        'league_id' => $league->id,
        'captain_id' => 1,
    ]);

    $this->team->users()->attach([1, 2, 3, 4, 5, 6, 7]);
});

test('admin can access edit page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.interclubs.teams.edit', $this->team))
        ->assertStatus(200);
});

test('committee member can access edit page', function (): void {
    $this->actingAs($this->committee_member)
        ->get(route('admin.interclubs.teams.edit', $this->team))
        ->assertStatus(200);
});

test('member cant access edit page', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.interclubs.teams.edit', $this->team))
        ->assertStatus(403);
});

test('unlogged user is redirected to login', function (): void {
    $this->get(route('admin.interclubs.teams.edit', $this->team))
        ->assertRedirect('/login');
});

test('admin can see edit button from team show view', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.interclubs.teams.show', $this->team->id))
        ->assertSee('Modifier');
});

test('committee member can see edit button from team show view', function (): void {
    $this->actingAs($this->committee_member)
        ->get(route('admin.interclubs.teams.show', $this->team->id))
        ->assertSee('Modifier');
});

test('member cant see edit button from team show view', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.interclubs.teams.show', $this->team->id))
        ->assertDontSee('Edit');
});

test('team name must be single letter', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('name', 'AA')
        ->call('save')
        ->assertHasErrors('name');
});

test('team name is required', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');
});

test('team must have at least one member', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('memberIds', [])
        ->call('save')
        ->assertHasErrors('memberIds');
});

test('the empty core message is rendered, not just raised', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('memberIds', [])
        ->call('save')
        ->assertSee('au moins un joueur');
});

/**
 * La liste crée une équipe sans joueur ni capitaine. Exiger un noyau à
 * l'édition rendait ces équipes impossibles à enregistrer : le bouton ne
 * faisait rien, l'erreur n'étant affichée nulle part.
 */
test('a team created without any player can still be saved', function (): void {
    $emptyTeam = Team::create([
        'name' => 'A',
        'season_id' => $this->team->season_id,
        'league_id' => $this->team->league_id,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $emptyTeam])
        ->set('name', 'B')
        ->call('save')
        ->assertHasNoErrors();

    expect($emptyTeam->fresh()->name)->toBe('B');
});

test('admin can toggle team member', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->call('toggleMember', 1)
        ->assertSet('memberIds', [2, 3, 4, 5, 6, 7]);
});

test('admin can set captain', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->call('setCaptain', 5)
        ->assertSet('captainId', 5);
});

test('admin can remove captain', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->call('removeCaptain')
        ->assertSet('captainId', null);
});

/**
 * Régression #27 : la division n'était pas modifiable, une erreur de saisie
 * imposait de supprimer l'équipe et de la recréer.
 */
describe('division correction', function (): void {
    beforeEach(function (): void {
        $this->team->load('league');

        $this->otherLeague = League::create([
            'division' => '3B',
            'level' => 'PROVINCIAL_BW',
            'category' => 'MEN',
            'season_id' => $this->team->season_id,
        ]);
    });

    it('lets an admin move a team that has no match yet', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
            ->set('leagueId', $this->otherLeague->id)
            ->call('save')
            ->assertHasNoErrors();

        expect($this->team->fresh()->league_id)->toBe($this->otherLeague->id);
    });

    it('rejects a division belonging to another season', function (): void {
        // Dates explicites : Season refuse les saisons qui se chevauchent.
        $foreignSeason = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(6)->startOfYear(),
            'end_at' => now()->subYears(6)->endOfYear(),
        ]);

        $foreignLeague = League::create([
            'division' => '2A',
            'level' => 'PROVINCIAL_BW',
            'category' => 'MEN',
            'season_id' => $foreignSeason->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
            ->set('leagueId', $foreignLeague->id)
            ->call('save')
            ->assertHasErrors('leagueId');
    });

    it('keeps the division locked once a match is scheduled', function (): void {
        $originalLeagueId = $this->team->league_id;

        Interclub::factory()->create([
            'season_id' => $this->team->season_id,
            'league_id' => $originalLeagueId,
            'visited_team_id' => $this->team->id,
            'total_players' => 4,
            'start_date_time' => now()->addDays(7),
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
            ->set('leagueId', $this->otherLeague->id)
            ->call('save');

        expect($this->team->fresh()->league_id)->toBe($originalLeagueId);
    });

    it('counts away matches too', function (): void {
        $opponent = Team::create([
            'name' => 'A',
            'season_id' => $this->team->season_id,
            'league_id' => $this->team->league_id,
            'club_id' => Club::factory()->create()->id,
        ]);

        Interclub::factory()->create([
            'season_id' => $this->team->season_id,
            'league_id' => $this->team->league_id,
            'visited_team_id' => $opponent->id,
            'visiting_team_id' => $this->team->id,
            'total_players' => 4,
            'start_date_time' => now()->addDays(7),
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
            ->assertViewHas('scheduledMatchCount', 1);
    });
});

describe('creating a division from the edit form', function (): void {
    it('moves the team to a division created on the spot', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
            ->set('newDivisionMode', true)
            ->set('newCategory', 'MEN')
            ->set('newLevel', 'PROVINCIAL_BW')
            ->set('newDivision', '5h')
            ->call('save')
            ->assertHasNoErrors();

        $league = League::where('season_id', $this->team->season_id)
            ->where('division', '5H')
            ->first();

        expect($league)->not->toBeNull();
        expect($this->team->fresh()->league_id)->toBe($league->id);
    });

    it('rejects a malformed division', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
            ->set('newDivisionMode', true)
            ->set('newCategory', 'MEN')
            ->set('newLevel', 'PROVINCIAL_BW')
            ->set('newDivision', 'Provincial BW 6')
            ->call('save')
            ->assertHasErrors('newDivision');

        expect(League::where('division', 'Provincial BW 6')->exists())->toBeFalse();
    });

    it('does not create a division when the team is locked by a scheduled match', function (): void {
        $originalLeagueId = $this->team->league_id;

        Interclub::factory()->create([
            'season_id' => $this->team->season_id,
            'league_id' => $originalLeagueId,
            'visited_team_id' => $this->team->id,
            'total_players' => 4,
            'start_date_time' => now()->addDays(7),
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
            ->set('newDivisionMode', true)
            ->set('newCategory', 'MEN')
            ->set('newLevel', 'PROVINCIAL_BW')
            ->set('newDivision', '5H')
            ->call('save');

        expect(League::where('division', '5H')->exists())->toBeFalse();
        expect($this->team->fresh()->league_id)->toBe($originalLeagueId);
    });
});
