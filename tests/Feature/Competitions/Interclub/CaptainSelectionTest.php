<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\Ranking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->captain = User::factory()->isCompetitor()->create();
    $this->player1 = User::factory()->isCompetitor()->create();
    $this->player2 = User::factory()->isCompetitor()->create();
    $this->outsider = User::factory()->isCompetitor()->create();

    $this->ownClub = Club::factory()->ownClub()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
    ]);

    $this->team->users()->attach([$this->captain->id, $this->player1->id, $this->player2->id]);

    $this->interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);
});

it('captain can select a player via select()', function (): void {
    $this->interclub->select($this->player1);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player1->id,
        'is_selected' => true,
    ]);
});

it('captain can deselect a player via deselect()', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->deselect($this->player1);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player1->id,
        'is_selected' => false,
    ]);
});

it('getSelectedPlayers returns only selected players', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->markAvailability($this->player2, InterclubAvailability::AVAILABLE);

    $selected = $this->interclub->getSelectedPlayers();

    expect($selected)->toHaveCount(1)
        ->and($selected->first()->id)->toBe($this->player1->id);
});

it('cannot select more players than total_players', function (): void {
    $players = User::factory()->isCompetitor()->count(5)->create();

    foreach ($players->take(4) as $p) {
        $this->interclub->select($p);
    }

    expect($this->interclub->isSelectionComplete())->toBeTrue();
    expect($this->interclub->getSelectedPlayers())->toHaveCount(4);
});

it('captain selection page requires authentication', function (): void {
    $this->get(route('admin.interclubs.captain-selection'))
        ->assertRedirect(route('login'));
});

it('captain can access the captain selection page', function (): void {
    $this->actingAs($this->captain)
        ->get(route('admin.interclubs.captain-selection'))
        ->assertOk();
});

/**
 * Régression #31 : la bulle d'infobulle du bouton « demander les dispos » était
 * rognée par la liste en overflow-hidden. L'action ne porte plus d'infobulle du
 * tout — elle est nommée dans le menu de ligne, qui est lu par un lecteur
 * d'écran et atteignable au pouce. La régression ne peut plus se reproduire :
 * il n'y a plus de bulle à rogner.
 */
it('names the availability request in the row menu rather than in a clipped tooltip', function (): void {
    $this->actingAs($this->captain)
        ->get(route('admin.interclubs.captain-selection'))
        ->assertOk()
        ->assertSee(__('Request availability'))
        ->assertDontSee('tooltip-top', escape: false)
        ->assertDontSee('tooltip-left', escape: false);
});

it('non-captain user cannot access the captain selection page', function (): void {
    $nonCaptain = User::factory()->isCompetitor()->create();

    $this->actingAs($nonCaptain)
        ->get(route('admin.interclubs.captain-selection'))
        ->assertForbidden();
});

it('admin who is also captain can access the captain selection page', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $this->team->update(['captain_id' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('admin.interclubs.captain-selection'))
        ->assertOk();
});

it('openSelection silently ignores past interclubs', function (): void {
    $past = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->subDays(1),
    ]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $past->id)
        ->assertSet('drawerSelection', false)
        ->assertSet('selectedInterclubId', null);
});

/**
 * The drawer used to build its roster from the team filter rather than from the
 * fixture it opens: opening an urgent match of another team from the alert
 * banner listed the *filtered* team's players, and saving attached them to the
 * wrong fixture. The roster follows the match now.
 */
it('lists the roster of the fixture team, not of the filtered team', function (): void {
    $otherTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
        'name' => 'ZZ',
    ]);

    $otherPlayer = User::factory()->isCompetitor()->create(['last_name' => 'Bravo']);
    $otherTeam->users()->attach($otherPlayer->id);

    $otherMatch = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $otherTeam->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(5),
    ]);

    $component = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection');

    // The filter sits on the first team; the fixture belongs to the other one.
    expect($component->get('selectedTeamId'))->toBe($this->team->id);

    $component->call('openSelection', $otherMatch->id);

    $roster = collect($component->viewData('roster'))->pluck('id');

    expect($roster)->toContain($otherPlayer->id)
        ->and($roster)->not->toContain($this->player1->id);
});

it('switches the page to the team of the fixture it opens', function (): void {
    $otherTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
        'name' => 'ZZ',
    ]);
    $otherTeam->users()->attach(User::factory()->isCompetitor()->create()->id);

    $otherMatch = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $otherTeam->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(5),
    ]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $otherMatch->id)
        ->assertSet('selectedTeamId', $otherTeam->id);
});

it('is_selector user can access the captain selection page and sees all teams', function (): void {
    $selector = User::factory()->isSelector()->create();

    $this->actingAs($selector)
        ->get(route('admin.interclubs.captain-selection'))
        ->assertOk();
});

it('matchDayMap scopes week numbers to own club teams only', function (): void {
    $otherTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
    ]);

    // Interclub for own team — week 10
    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'week_number' => 10,
        'start_date_time' => now()->addDays(7),
    ]);

    // Interclub for other team only — week 20
    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $otherTeam->id,
        'week_number' => 20,
        'start_date_time' => now()->addDays(14),
    ]);

    $map = Interclub::matchDayMap(
        $this->season->id,
        [$this->team->id]
    );

    expect($map)->toHaveKey(10)
        ->and($map)->not->toHaveKey(20);
});

it('shows team players contact details to the captain even when unshared', function (): void {
    // player1 shares nothing (opt-in default) — the captain still needs their
    // contact to organise the selection.
    $this->player1->update([
        'phone_number' => '0470999888',
        'email' => 'captain.player1@club.be',
        'contact_visibility' => null,
    ]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->assertSee('0470999888')
        ->assertSee('captain.player1@club.be');
});

// ── substitute search: explain why the list is empty (I2) ─────────────────────

describe('substitute search — explains the silent filtering (I2)', function (): void {
    // The substitute search is a selector/committee feature (a plain captain has
    // no access to it), so these tests act as a selector.
    beforeEach(function (): void {
        $this->selector = User::factory()->isSelector()->create();
    });

    function openSelectorSearch(User $selector, int $interclubId, int $teamId, string $search)
    {
        return Livewire::actingAs($selector)
            ->test('pages::club-events.interclubs.captain-selection')
            ->set('selectedTeamId', $teamId)
            ->call('openSelection', $interclubId)
            ->set('search', $search);
    }

    it('tells the selector the team category is hiding matching players', function (): void {
        // beforeEach sets the team up in a MEN league. A female competitor whose
        // name matches the search is silently filtered out by category.
        // Ranking is pinned: isCompetitor() draws it at random and User::interclubEligible()
        // drops NA, so an unpinned ranking silently empties the candidate pool ~1 time in 18
        // and leaves searchNote null.
        User::factory()->isCompetitor()->create([
            'gender' => Gender::WOMEN,
            'last_name' => 'Zoravitch',
            'ranking' => Ranking::C0->value,
        ]);

        $component = openSelectorSearch($this->selector, $this->interclub->id, $this->team->id, 'Zoravitch');

        expect($component->viewData('searchResults'))->toBeEmpty()
            ->and($component->viewData('searchNote'))->not->toBeNull();

        $component->assertSee(__('this team only lines up men'));
    });

    it('tells the selector a matching player is already lined up elsewhere', function (): void {
        // A male competitor of the right category, but already selected in another
        // team the same week -> blocked and silently removed from the results.
        $blocked = User::factory()->isCompetitor()->create([
            'gender' => Gender::MEN,
            'last_name' => 'Blockman',
            'ranking' => Ranking::C0->value,
        ]);

        $otherTeam = Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'club_id' => $this->ownClub->id,
        ]);

        $otherMatch = Interclub::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'visited_team_id' => $otherTeam->id,
            'week_number' => $this->interclub->week_number,
            'total_players' => 4,
            'start_date_time' => now()->addDays(7),
        ]);
        $otherMatch->users()->attach($blocked->id, ['is_selected' => true]);

        $component = openSelectorSearch($this->selector, $this->interclub->id, $this->team->id, 'Blockman');

        expect($component->viewData('searchResults'))->toBeEmpty()
            ->and($component->viewData('searchNote'))->not->toBeNull();

        $component->assertSee(__('some are already selected here or lined up in another team this week'));
    });

    it('stays silent when no competitor matches the search at all', function (): void {
        $component = openSelectorSearch($this->selector, $this->interclub->id, $this->team->id, 'Nobodyhere');

        expect($component->viewData('searchResults'))->toBeEmpty()
            ->and($component->viewData('searchNote'))->toBeNull();

        $component->assertSee(__('No player found.'));
    });

    it('still returns an eligible substitute of the right category', function (): void {
        $man = User::factory()->isCompetitor()->create([
            'gender' => Gender::MEN,
            'last_name' => 'Eligibleman',
            'ranking' => Ranking::C0->value,
        ]);

        $component = openSelectorSearch($this->selector, $this->interclub->id, $this->team->id, 'Eligibleman');

        expect(collect($component->viewData('searchResults'))->pluck('id'))->toContain($man->id)
            ->and($component->viewData('searchNote'))->toBeNull();
    });
});

// ── Status logic tests ──────────────────────────────────────────────────────

/** Helper: render the captain-selection component and return status for a given match */
function matchStatus(int $interclubId, User $captain, int $teamId): string
{
    $component = Livewire::actingAs($captain)
        ->test('pages::club-events.interclubs.captain-selection');

    $teamsData = $component->viewData('teamsData');
    $teamData = collect($teamsData)->firstWhere('id', $teamId);
    $match = collect($teamData['matches'])->firstWhere('id', $interclubId);

    return $match['status'];
}

it('returns past status for a match in the past', function (): void {
    $interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->subDays(1),
    ]);

    expect(matchStatus($interclub->id, $this->captain, $this->team->id))->toBe('past');
});

it('returns future status when match is beyond 14 days and no availability or selection', function (): void {
    $interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(20),
    ]);

    expect(matchStatus($interclub->id, $this->captain, $this->team->id))->toBe('future');
});

it('returns urgent status when match is within 14 days and not enough available players', function (): void {
    // $this->interclub: +7 days, 3 team members, 0 available → urgent
    expect(matchStatus($this->interclub->id, $this->captain, $this->team->id))->toBe('urgent');
});

it('returns actionable status when enough players are available even if no selection made', function (): void {
    $interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 2,
        'start_date_time' => now()->addDays(20),
    ]);

    $interclub->markAvailability($this->player1, InterclubAvailability::AVAILABLE);
    $interclub->markAvailability($this->player2, InterclubAvailability::AVAILABLE);

    expect(matchStatus($interclub->id, $this->captain, $this->team->id))->toBe('actionable');
});

it('returns actionable status when selection is complete but not yet confirmed', function (): void {
    // +7 days (urgent zone), but selection is complete → actionable takes priority
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->update(['total_players' => 2]);

    expect(matchStatus($this->interclub->id, $this->captain, $this->team->id))->toBe('actionable');
});

it('returns confirmed status when selection_confirmed_at is set', function (): void {
    $this->interclub->users()->attach($this->player1->id, [
        'is_selected' => true,
        'selection_confirmed_at' => now(),
    ]);

    expect(matchStatus($this->interclub->id, $this->captain, $this->team->id))->toBe('confirmed');
});

it('has_played flag counts as a played match in matchesPlayedCount', function (): void {
    $past = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->subDays(7),
    ]);

    $past->users()->attach($this->player1->id, ['is_selected' => true, 'has_played' => true]);

    $anotherPast = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->subDays(14),
    ]);

    // is_selected but has_played = false — should NOT be counted
    $anotherPast->users()->attach($this->player1->id, ['is_selected' => true, 'has_played' => false]);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $past->id,
        'user_id' => $this->player1->id,
        'has_played' => true,
    ]);
    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $anotherPast->id,
        'user_id' => $this->player1->id,
        'has_played' => false,
    ]);
});

// ── saveSelection: completeness + update-mode detection ─────────────────────

it('does not open the notify modal when saving an incomplete selection', function (): void {
    $this->interclub->update(['total_players' => 2]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->player1->id)
        ->call('saveSelection')
        ->assertSet('modalMessage', false)
        ->assertSet('isUpdateMode', false);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player1->id,
        'is_selected' => true,
    ]);
});

it('opens the notify modal when saving a complete first-time selection', function (): void {
    $this->interclub->update(['total_players' => 2]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->player1->id)
        ->call('togglePlayer', $this->player2->id)
        ->call('saveSelection')
        ->assertSet('modalMessage', true)
        ->assertSet('isUpdateMode', false);
});

it('detects added and removed players when editing an already confirmed selection', function (): void {
    $this->interclub->update(['total_players' => 2]);
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->users()->updateExistingPivot($this->player1->id, ['selection_confirmed_at' => now()]);
    $this->interclub->users()->updateExistingPivot($this->player2->id, ['selection_confirmed_at' => now()]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->player1->id)
        ->call('togglePlayer', $this->outsider->id)
        ->call('saveSelection')
        ->assertSet('isUpdateMode', true)
        ->assertSet('pendingAddedIds', [$this->outsider->id])
        ->assertSet('pendingRemovedIds', [$this->player1->id])
        ->assertSet('modalMessage', true);
});

it('does not reopen the modal when re-saving an unchanged confirmed selection', function (): void {
    $this->interclub->update(['total_players' => 2]);
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->users()->updateExistingPivot($this->player1->id, ['selection_confirmed_at' => now()]);
    $this->interclub->users()->updateExistingPivot($this->player2->id, ['selection_confirmed_at' => now()]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('saveSelection')
        ->assertSet('modalMessage', false)
        ->assertSet('isUpdateMode', false);
});

/*
|--------------------------------------------------------------------------
| Lot 2 — structure de la vue
|--------------------------------------------------------------------------
|
| DS-A: the team determines the object of the page (exactly one, never none),
| so it is navigation, not a filter. It leaves the filter drawer and becomes a
| permanent control; the season stays a filter because it can be cleared.
|
*/
it('names the team it is showing', function (): void {
    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->assertSee($this->team->name);
});

it('switches team through a first-class action rather than the filter drawer', function (): void {
    $otherTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
        'name' => 'ZZ',
    ]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('selectTeam', $otherTeam->id)
        ->assertSet('selectedTeamId', $otherTeam->id);
});

it('never offers the team as a removable filter chip', function (): void {
    $otherTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
        'name' => 'ZZ',
    ]);

    // Switch away from the default, which is where a chip used to appear.
    $chips = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('selectTeam', $otherTeam->id)
        ->viewData('filterChips');

    expect(collect($chips)->pluck('key'))->not->toContain('selectedTeamId');
});

it('refuses to switch to a team the user does not lead', function (): void {
    $foreign = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => User::factory()->isCompetitor()->create()->id,
        'club_id' => $this->ownClub->id,
        'name' => 'XX',
    ]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('selectTeam', $foreign->id)
        ->assertSet('selectedTeamId', $this->team->id);
});

it('splits the fixtures into what needs doing, what is coming and what is played', function (): void {
    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->subDays(10),
    ]);

    $groups = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->viewData('matchGroups');

    // « Sous contrôle » sort de « À venir » : une composition envoyée n'est pas
    // une échéance qui approche, c'est une échéance réglée.
    expect(array_keys($groups))->toBe(['todo', 'controlled', 'upcoming', 'played'])
        ->and($groups['played'])->toHaveCount(1)
        ->and(collect($groups['todo'])->pluck('is_past'))->not->toContain(true);
});

it('names the availability request instead of hiding it in a tooltip', function (): void {
    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->assertSee(__('Request availability'));
});

it('keeps the alert banner for other teams only', function (): void {
    // The urgent fixture of the team already on screen is a row in the list;
    // repeating it in the banner is noise.
    $urgent = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(3),
    ]);

    $alerts = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->viewData('alertMatches');

    expect(collect($alerts)->pluck('id'))->not->toContain($urgent->id);
});

/*
 * Requesting availabilities e-mails the whole team. It used to fire straight
 * from a bare icon, so five clicks were five rounds of mail to the same people.
 */
it('asks for confirmation before mailing the team', function (): void {
    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('confirmAvailabilityRequest', $this->interclub->id)
        ->assertSet('availabilityRequestModal', true)
        ->assertSet('availabilityRequestId', $this->interclub->id);
});

it('refuses to arm the confirmation for a fixture the user cannot reach', function (): void {
    $foreignTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => User::factory()->isCompetitor()->create()->id,
        'club_id' => $this->ownClub->id,
    ]);
    $foreign = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $foreignTeam->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('confirmAvailabilityRequest', $foreign->id)
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Mode « Par journée »
|--------------------------------------------------------------------------
|
| Une saison est une matrice équipe × journée. L'écran en lisait une ligne ;
| il lit désormais aussi une colonne, ce qui remplace le centre de contrôle.
| La colonne reste bornée aux équipes que la personne peut déjà atteindre :
| le mode ne montre jamais plus que le sélecteur d'équipe ne montrait.
|
*/
it('reads the season by team by default', function (): void {
    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->assertSet('viewMode', 'team');
});

it('switches to reading the season by match day', function (): void {
    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('setViewMode', 'day')
        ->assertSet('viewMode', 'day');
});

it('refuses a view mode it does not know', function (): void {
    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('setViewMode', 'nonsense')
        ->assertSet('viewMode', 'team');
});

it('lists every accessible team playing the selected match day', function (): void {
    $second = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
        'name' => 'ZZ',
    ]);
    $second->users()->attach(User::factory()->isCompetitor()->create()->id);

    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $second->id,
        'week_number' => $this->interclub->week_number,
        'total_players' => 4,
        'start_date_time' => $this->interclub->start_date_time,
    ]);

    $component = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('setViewMode', 'day')
        ->call('selectDay', $this->interclub->week_number);

    $teamIds = collect($component->viewData('dayGroups'))
        ->flatten(1)
        ->pluck('team_id');

    expect($teamIds)->toContain($this->team->id)
        ->and($teamIds)->toContain($second->id);
});

it('never leaks a team the user does not lead into the match day view', function (): void {
    $foreignTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => User::factory()->isCompetitor()->create()->id,
        'club_id' => $this->ownClub->id,
        'name' => 'XX',
    ]);
    $foreignTeam->users()->attach(User::factory()->isCompetitor()->create()->id);

    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $foreignTeam->id,
        'week_number' => $this->interclub->week_number,
        'total_players' => 4,
        'start_date_time' => $this->interclub->start_date_time,
    ]);

    $component = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('setViewMode', 'day')
        ->call('selectDay', $this->interclub->week_number);

    $teamIds = collect($component->viewData('dayGroups'))->flatten(1)->pluck('team_id');

    expect($teamIds)->not->toContain($foreignTeam->id);
});

it('shows every own-club team of the match day to a club-wide selector', function (): void {
    $selector = User::factory()->isSelector()->create();

    $otherCaptainTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => User::factory()->isCompetitor()->create()->id,
        'club_id' => $this->ownClub->id,
        'name' => 'XX',
    ]);
    $otherCaptainTeam->users()->attach(User::factory()->isCompetitor()->create()->id);

    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $otherCaptainTeam->id,
        'week_number' => $this->interclub->week_number,
        'total_players' => 4,
        'start_date_time' => $this->interclub->start_date_time,
    ]);

    $component = Livewire::actingAs($selector)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('setViewMode', 'day')
        ->call('selectDay', $this->interclub->week_number);

    $teamIds = collect($component->viewData('dayGroups'))->flatten(1)->pluck('team_id');

    expect($teamIds)->toContain($otherCaptainTeam->id)
        ->and($teamIds)->toContain($this->team->id);
});

/*
|--------------------------------------------------------------------------
| Double engagement — une rencontre par catégorie et par semaine
|--------------------------------------------------------------------------
|
| Règle du club : personne ne joue deux fois dans la même catégorie la même
| semaine. Les vétérans jouent pendant les semaines de repos des seniors, donc
| le cas ne se pose jamais entre eux. Les dames, elles, jouent en parallèle :
| une joueuse peut être alignée le vendredi en dames et le samedi en senior.
|
| Le contrôle comparait les semaines sans regarder la catégorie, et refusait
| donc ce cumul-là sur les dix-huit semaines où les deux calendriers se croisent.
|
*/
it('blocks a player already lined up in another team of the same category that week', function (): void {
    $sameCategoryTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
        'name' => 'ZZ',
    ]);
    $sameCategoryTeam->users()->attach($this->player1->id);

    $clash = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $sameCategoryTeam->id,
        'week_number' => $this->interclub->week_number,
        'total_players' => 4,
        'start_date_time' => $this->interclub->start_date_time,
    ]);
    $clash->users()->attach($this->player1->id, ['is_selected' => true]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->player1->id)
        ->assertSet('selectedPlayerIds', []);
});

it('lets a woman play in her ladies team and a senior team the same week', function (): void {
    $ladiesLeague = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'WOMEN',
    ]);

    $ladiesTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $ladiesLeague->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
        'name' => 'ZZ',
    ]);
    $ladiesTeam->users()->attach($this->player1->id);

    // Vendredi en dames…
    $ladiesFixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $ladiesLeague->id,
        'visited_team_id' => $ladiesTeam->id,
        'week_number' => $this->interclub->week_number,
        'total_players' => 4,
        'start_date_time' => $this->interclub->start_date_time->copy()->subDay(),
    ]);
    $ladiesFixture->users()->attach($this->player1->id, ['is_selected' => true]);

    // …samedi en senior : légitime.
    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->player1->id)
        ->assertSet('selectedPlayerIds', [$this->player1->id]);
});

it('does not flag a cross-category selection as already lined up', function (): void {
    $ladiesLeague = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'WOMEN',
    ]);
    $ladiesTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $ladiesLeague->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->ownClub->id,
        'name' => 'ZZ',
    ]);
    $ladiesTeam->users()->attach($this->player1->id);

    $ladiesFixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $ladiesLeague->id,
        'visited_team_id' => $ladiesTeam->id,
        'week_number' => $this->interclub->week_number,
        'total_players' => 4,
        'start_date_time' => $this->interclub->start_date_time->copy()->subDay(),
    ]);
    $ladiesFixture->users()->attach($this->player1->id, ['is_selected' => true]);

    $roster = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->viewData('roster');

    expect(collect($roster)->firstWhere('id', $this->player1->id)['is_blocked'])->toBeFalse();
});

/*
 * Les bulles de journée affichent un indice chronologique (matchDayMap) mais
 * étaient ordonnées par numéro de semaine. Tant qu'une seule catégorie joue les
 * deux coïncident ; dès que seniors et vétérans alternent, les bulles sortent
 * mélangées — 1, 3, 5, 2, 4 — et les flèches ◀ ▶ sautent d'une journée à l'autre.
 */
it('orders the match day chips by kick-off, not by week number', function (): void {
    // Une semaine au numéro plus élevé peut se jouer plus tôt : c'est le cas dès
    // qu'une catégorie occupe les semaines de repos d'une autre.
    $early = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'week_number' => 40,
        'total_players' => 4,
        'start_date_time' => now()->addDays(3),
    ]);

    $late = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'week_number' => 4,
        'total_players' => 4,
        'start_date_time' => now()->addDays(30),
    ]);

    $matchDays = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->viewData('matchDays');

    $positions = [
        array_search($early->week_number, $matchDays, true),
        array_search($late->week_number, $matchDays, true),
    ];

    expect($positions[0])->toBeLessThan($positions[1]);
});

it('lands on the soonest match day needing attention, not the lowest numbered', function (): void {
    // La journée 40 se joue dans trois jours et n'a rien de prêt ; la journée 4
    // est à un mois. C'est la 40 qui attend le capitaine.
    $soon = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'week_number' => 40,
        'total_players' => 4,
        'start_date_time' => now()->addDays(3),
    ]);

    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'week_number' => 4,
        'total_players' => 4,
        'start_date_time' => now()->addDays(30),
    ]);

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('setViewMode', 'day')
        ->assertSet('selectedMatchDay', $soon->week_number);
});
