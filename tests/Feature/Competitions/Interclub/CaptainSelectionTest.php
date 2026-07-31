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
 * Régression #31 : la liste des rencontres est en overflow-hidden, une bulle
 * positionnée au-dessus du bouton y était rognée et illisible.
 */
it('anchors the availability request tooltip to the left so the list does not clip it', function (): void {
    $this->actingAs($this->captain)
        ->get(route('admin.interclubs.captain-selection'))
        ->assertOk()
        ->assertSee('lg:tooltip-left', escape: false)
        ->assertDontSee('lg:tooltip-top', escape: false);
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
