<?php

declare(strict_types=1);

use App\Enums\InterclubAvailability;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->captain = User::factory()->isCompetitor()->create();
    $this->player1 = User::factory()->isCompetitor()->create();
    $this->player2 = User::factory()->isCompetitor()->create();
    $this->outsider = User::factory()->isCompetitor()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
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
