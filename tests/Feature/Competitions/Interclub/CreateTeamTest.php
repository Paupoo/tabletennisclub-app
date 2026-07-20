<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Livewire\Livewire;

beforeEach(function (): void {
    // Dates explicites et passées : Season refuse les saisons qui se chevauchent,
    // et certains tests créent ensuite une saison active sur l'année en cours.
    $this->season = Season::factory()->create([
        'is_active' => false,
        'start_at' => now()->subYears(5)->startOfYear(),
        'end_at' => now()->subYears(5)->endOfYear(),
    ]);

    $this->user = User::factory()
        ->isNotCompetitor()
        ->create();

    $this->committee_member = User::factory()
        ->isCommitteeMember()
        ->isNotCompetitor()
        ->create();

    $this->admin = User::factory()
        ->isAdmin()
        ->isNotCompetitor()
        ->create();
});

test('admin or committee member can access team list page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.interclubs.teams'))
        ->assertStatus(200);

    $this->actingAs($this->committee_member)
        ->get(route('admin.interclubs.teams'))
        ->assertStatus(200);
});

test('unlogged user is redirected to login', function (): void {
    $this->get(route('admin.interclubs.teams'))
        ->assertRedirect('/login');
});

test('member cannot access team list page', function (): void {
    $this->actingAs($this->user)
        ->get(route('admin.interclubs.teams'))
        ->assertForbidden();
});

test('admin can validate team creation fields via Livewire', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.index')
        ->set('newTeamName', '')
        ->set('newLeagueId', null)
        ->call('createTeam')
        ->assertHasErrors(['newTeamName', 'newLeagueId']);
});

test('creating a new division requires a category, a level and a well-formed division', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.index')
        ->set('newDivisionMode', true)
        ->set('newTeamName', '')
        ->set('newCategory', '')
        ->set('newLevel', '')
        ->set('newDivision', '')
        ->call('createTeam')
        ->assertHasErrors(['newTeamName', 'newCategory', 'newLevel', 'newDivision']);
});

/**
 * Régression #27 : la division était un champ libre alimentant un
 * League::firstOrCreate, donc une faute de frappe créait silencieusement une
 * division inexistante (« Provincial BW 6 »).
 */
describe('divisions are never created implicitly', function (): void {
    beforeEach(function (): void {
        $this->activeSeason = makeActiveSeason();
        Club::factory()->ownClub()->create();

        $this->league = League::factory()->create([
            'season_id' => $this->activeSeason->id,
            'division' => '3B',
            'level' => 'PROVINCIAL_BW',
            'category' => 'MEN',
        ]);
    });

    it('attaches the team to an existing division without creating one', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.index')
            ->set('newTeamName', 'A')
            ->set('newLeagueId', $this->league->id)
            ->call('createTeam')
            ->assertHasNoErrors();

        expect(League::where('season_id', $this->activeSeason->id)->count())->toBe(1);
        expect(Team::where('league_id', $this->league->id)->count())->toBe(1);
    });

    it('rejects a division that does not exist for the season', function (): void {
        // Dates explicites : Season refuse les saisons qui se chevauchent.
        $pastSeason = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(3)->startOfYear(),
            'end_at' => now()->subYears(3)->endOfYear(),
        ]);
        $otherLeague = League::factory()->create(['season_id' => $pastSeason->id]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.index')
            ->set('newTeamName', 'A')
            ->set('newLeagueId', $otherLeague->id)
            ->call('createTeam')
            ->assertHasErrors(['newLeagueId']);

        expect(Team::count())->toBe(0);
    });

    it('rejects a malformed division when creating one explicitly', function (string $division): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.index')
            ->set('newDivisionMode', true)
            ->set('newTeamName', 'A')
            ->set('newCategory', 'MEN')
            ->set('newLevel', 'PROVINCIAL_BW')
            ->set('newDivision', $division)
            ->call('createTeam')
            ->assertHasErrors(['newDivision']);

        expect(League::where('season_id', $this->activeSeason->id)->count())->toBe(1);
    })->with(['Provincial BW 6', '3 B', 'beaucoup-trop-long', '']);

    it('creates a division only when explicitly asked, and uppercases it', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.interclubs.teams.index')
            ->set('newDivisionMode', true)
            ->set('newTeamName', 'B')
            ->set('newCategory', 'MEN')
            ->set('newLevel', 'PROVINCIAL_BW')
            ->set('newDivision', '4d')
            ->call('createTeam')
            ->assertHasNoErrors();

        expect(League::where('season_id', $this->activeSeason->id)->where('division', '4D')->exists())->toBeTrue();
    });
});

test('member cant call createTeam via Livewire', function (): void {
    Livewire::actingAs($this->user)
        ->test('pages::club-events.interclubs.teams.index')
        ->set('newTeamName', 'A')
        ->set('newCategory', 'MEN')
        ->set('newLevel', 'NATIONAL')
        ->set('newDivision', '1A')
        ->call('createTeam')
        ->assertStatus(403);
});
