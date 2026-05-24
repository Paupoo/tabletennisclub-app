<?php

declare(strict_types=1);

use App\Enums\InterclubAvailability;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->player = User::factory()->isCompetitor()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
    ]);

    $this->team->users()->attach($this->player->id);
});

it('marks all future interclubs as available in bulk', function (): void {
    $match1 = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(7),
    ]);

    $match2 = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(14),
    ]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->call('bulkMarkAvailability', 'available');

    foreach ([$match1->id, $match2->id] as $interclubId) {
        $this->assertDatabaseHas('interclub_user', [
            'interclub_id' => $interclubId,
            'user_id' => $this->player->id,
            'availability' => InterclubAvailability::AVAILABLE->value,
        ]);
    }
});

it('does not update past interclubs', function (): void {
    $past = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->subDays(1),
    ]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->call('bulkMarkAvailability', 'available');

    $this->assertDatabaseMissing('interclub_user', [
        'interclub_id' => $past->id,
        'user_id' => $this->player->id,
        'availability' => InterclubAvailability::AVAILABLE->value,
    ]);
});

it('marks all future interclubs as maybe in bulk', function (): void {
    $match = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(5),
    ]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->call('bulkMarkAvailability', 'maybe');

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $match->id,
        'user_id' => $this->player->id,
        'availability' => InterclubAvailability::MAYBE->value,
    ]);
});

it('marks all future interclubs as unavailable in bulk', function (): void {
    $match = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(5),
    ]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->call('bulkMarkAvailability', 'unavailable');

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $match->id,
        'user_id' => $this->player->id,
        'availability' => InterclubAvailability::UNAVAILABLE->value,
    ]);
});

it('updates matches across multiple teams the player belongs to', function (): void {
    $team2 = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
    ]);
    $team2->users()->attach($this->player->id);

    $matchTeam1 = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(7),
    ]);

    $matchTeam2 = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $team2->id,
        'start_date_time' => now()->addDays(7),
    ]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->call('bulkMarkAvailability', 'available');

    foreach ([$matchTeam1->id, $matchTeam2->id] as $interclubId) {
        $this->assertDatabaseHas('interclub_user', [
            'interclub_id' => $interclubId,
            'user_id' => $this->player->id,
            'availability' => InterclubAvailability::AVAILABLE->value,
        ]);
    }
});
