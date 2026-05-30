<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\InterclubAvailability;
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
    $this->player = User::factory()->isCompetitor()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
    ]);

    $this->team->users()->attach([$this->captain->id, $this->player->id]);

    $this->interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);
});

it('marks a player as available via HasAvailability trait', function (): void {
    $this->interclub->markAvailability($this->player, InterclubAvailability::AVAILABLE);

    expect($this->interclub->users()->where('users.id', $this->player->id)->first()->registration->availability)
        ->toBe(InterclubAvailability::AVAILABLE->value);
});

it('sets is_subscribed to true when player marks available', function (): void {
    $this->interclub->markAvailability($this->player, InterclubAvailability::AVAILABLE);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player->id,
        'availability' => InterclubAvailability::AVAILABLE->value,
        'is_subscribed' => true,
    ]);
});

it('sets is_subscribed to false when player marks unavailable', function (): void {
    $this->interclub->markAvailability($this->player, InterclubAvailability::UNAVAILABLE);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player->id,
        'availability' => InterclubAvailability::UNAVAILABLE->value,
        'is_subscribed' => false,
    ]);
});

it('saves an optional note with availability', function (): void {
    $this->interclub->markAvailability($this->player, InterclubAvailability::MAYBE, 'En déplacement ce soir-là');

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player->id,
        'availability' => InterclubAvailability::MAYBE->value,
        'availability_note' => 'En déplacement ce soir-là',
    ]);
});

it('allows changing availability from available to unavailable', function (): void {
    $this->interclub->markAvailability($this->player, InterclubAvailability::AVAILABLE);
    $this->interclub->markAvailability($this->player, InterclubAvailability::UNAVAILABLE);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player->id,
        'availability' => InterclubAvailability::UNAVAILABLE->value,
    ]);
});

it('returns correct available players collection', function (): void {
    $this->interclub->markAvailability($this->player, InterclubAvailability::AVAILABLE);
    $this->interclub->markAvailability($this->captain, InterclubAvailability::UNAVAILABLE);

    $available = $this->interclub->getAvailablePlayers();

    expect($available)->toHaveCount(1)
        ->and($available->first()->id)->toBe($this->player->id);
});

it('detects selection is complete when enough players selected', function (): void {
    $players = User::factory()->isCompetitor()->count(4)->create();
    foreach ($players as $p) {
        $this->interclub->select($p);
    }

    expect($this->interclub->isSelectionComplete())->toBeTrue();
});

it('detects selection is incomplete when not enough players selected', function (): void {
    $this->interclub->select($this->player);

    expect($this->interclub->isSelectionComplete())->toBeFalse();
});
