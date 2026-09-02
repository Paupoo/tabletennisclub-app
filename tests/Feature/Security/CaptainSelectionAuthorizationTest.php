<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Captain selection — horizontal privilege escalation
|--------------------------------------------------------------------------
|
| mount() only checked that the caller captained *some* team. The public
| Livewire entry points then took an arbitrary interclub id without ever
| re-checking it belonged to a team the caller leads, so any captain could
| rewrite any other team's lineup — and trigger emails to its players.
|
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->ownClub = Club::factory()->ownClub()->create();

    $makeTeamWithMatch = function (User $captain): array {
        $team = Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'captain_id' => $captain->id,
            'club_id' => $this->ownClub->id,
        ]);

        $team->users()->attach($captain->id);

        $interclub = Interclub::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'visited_team_id' => $team->id,
            'total_players' => 4,
            'start_date_time' => now()->addDays(7),
        ]);

        return [$team, $interclub];
    };

    $this->captainA = User::factory()->isCompetitor()->create();
    $this->captainB = User::factory()->isCompetitor()->create();

    [$this->teamA, $this->matchA] = $makeTeamWithMatch($this->captainA);
    [$this->teamB, $this->matchB] = $makeTeamWithMatch($this->captainB);
});

it('forbids a captain from opening another team lineup', function (): void {
    Livewire::actingAs($this->captainA)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->matchB->id)
        ->assertForbidden();
});

/**
 * The request now goes through a confirmation, so the fixture is authorised
 * twice: once to arm the modal, once to actually send. Both gates are checked —
 * the second one matters most, because the id survives in component state.
 */
it('forbids a captain from requesting availability for another team', function (): void {
    Livewire::actingAs($this->captainA)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('confirmAvailabilityRequest', $this->matchB->id)
        ->assertForbidden();
});

it('prevents the armed availability request from being retargeted client-side', function (): void {
    Livewire::actingAs($this->captainA)
        ->test('pages::club-events.interclubs.captain-selection')
        ->set('availabilityRequestId', $this->matchB->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('allows a captain to open their own team lineup', function (): void {
    Livewire::actingAs($this->captainA)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->matchA->id)
        ->assertOk()
        ->assertSet('selectedInterclubId', $this->matchA->id)
        ->assertSet('drawerSelection', true);
});

it('allows an admin to open any team lineup', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();

    Livewire::actingAs($admin)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->matchB->id)
        ->assertOk()
        ->assertSet('selectedInterclubId', $this->matchB->id);
});

it('prevents the selected interclub from being set client-side', function (): void {
    Livewire::actingAs($this->captainA)
        ->test('pages::club-events.interclubs.captain-selection')
        ->set('selectedInterclubId', $this->matchB->id);
})->throws(CannotUpdateLockedPropertyException::class);
