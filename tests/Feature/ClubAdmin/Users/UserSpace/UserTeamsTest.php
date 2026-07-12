<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const USER_TEAMS_COMPONENT = 'pages::club-admin.users.user-space.user-teams';

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
    ]);

    $this->captain = User::factory()->isCompetitor()->create();
    $this->member = User::factory()->isCompetitor()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
    ]);

    $this->team->users()->attach([$this->captain->id, $this->member->id]);
});

it('shows an empty state when the member has no team', function (): void {
    $loner = User::factory()->create();

    Livewire::actingAs($loner)
        ->test(USER_TEAMS_COMPONENT, ['user' => $loner])
        ->assertSee(__('You are not part of any team this season.'));
});

it('shows the real roster with the captain badge', function (): void {
    Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSee($this->team->fullName())
        ->assertSee($this->captain->first_name)
        ->assertSee($this->member->first_name)
        ->assertSee(__('Captain'))
        ->assertSee(__('(you)'));
});

it('lists the upcoming matches of the team with the member availability', function (): void {
    $interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(7),
    ]);

    $interclub->markAvailability($this->member, InterclubAvailability::AVAILABLE);

    Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSee($interclub->opponentTeam()->fullName())
        ->assertSee(InterclubAvailability::AVAILABLE->label());
});

it('invites the member to set a missing availability', function (): void {
    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(7),
    ]);

    Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSee(__('Set availability'));
});

it('never shows past matches', function (): void {
    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->subDays(7),
    ]);

    Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSee(__('No upcoming matches for this team.'));
});

it('lets the member switch between their teams', function (): void {
    $otherLeague = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'VETERANS',
    ]);
    $otherTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $otherLeague->id,
    ]);
    $otherTeam->users()->attach($this->member->id);

    Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->set('selectedTeamId', $otherTeam->id)
        ->assertSee($otherTeam->fullName())
        ->assertSee(__('Veterans'));
});
