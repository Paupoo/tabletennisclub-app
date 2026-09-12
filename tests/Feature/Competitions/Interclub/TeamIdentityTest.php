<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * What identifies one of our teams: a letter, in a category, in a season.
 *
 * The club fields A, B, C, D, E in men and A, B, C in veterans — one letter each,
 * spread across divisions. Keying on the division instead would only catch a
 * duplicate when both teams happen to land in the same one.
 *
 * The rule stops at our own club on purpose. Opponent teams come from the
 * federation through `firstOrCreate`, and tightening their key would let two real
 * teams collapse into one, taking their fixtures with them. A duplicate row on an
 * opponent is harmless; a silent merge corrupts the calendar.
 */
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->ourClub = Club::factory()->ownClub()->create();

    $this->leagueIn = fn (string $category, string $division = '3B'): League => League::factory()->create([
        'season_id' => $this->season->id,
        'category' => $category,
        'division' => $division,
    ]);
});

it('refuses a second team carrying the same letter in the category', function (): void {
    $first = ($this->leagueIn)('MEN', 'P3B');
    $second = ($this->leagueIn)('MEN', 'P4A');

    Team::create([
        'name' => 'A', 'season_id' => $this->season->id,
        'league_id' => $first->id, 'club_id' => $this->ourClub->id,
    ]);

    expect(fn () => Team::create([
        'name' => 'A', 'season_id' => $this->season->id,
        'league_id' => $second->id, 'club_id' => $this->ourClub->id,
    ]))->toThrow(DomainException::class);
});

it('lets the same letter stand in another category', function (): void {
    foreach (['MEN', 'VETERANS', 'WOMEN'] as $category) {
        Team::create([
            'name' => 'A', 'season_id' => $this->season->id,
            'league_id' => ($this->leagueIn)($category)->id,
            'club_id' => $this->ourClub->id,
        ]);
    }

    expect(Team::where('name', 'A')->count())->toBe(3);
});

it('leaves opponent clubs alone', function (): void {
    $rival = Club::factory()->create(['is_own_club' => false]);
    $league = ($this->leagueIn)('MEN');

    Team::create(['name' => 'A', 'season_id' => $this->season->id, 'league_id' => $league->id, 'club_id' => $rival->id]);
    Team::create(['name' => 'A', 'season_id' => $this->season->id, 'league_id' => ($this->leagueIn)('MEN', '4C')->id, 'club_id' => $rival->id]);

    expect(Team::where('club_id', $rival->id)->count())->toBe(2);
});

it('does not reach across seasons', function (): void {
    $past = Season::factory()->create([
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);

    Team::create([
        'name' => 'A', 'season_id' => $this->season->id,
        'league_id' => ($this->leagueIn)('MEN')->id, 'club_id' => $this->ourClub->id,
    ]);
    Team::create([
        'name' => 'A', 'season_id' => $past->id,
        'league_id' => League::factory()->create(['season_id' => $past->id, 'category' => 'MEN'])->id,
        'club_id' => $this->ourClub->id,
    ]);

    expect(Team::where('name', 'A')->count())->toBe(2);
});

it('does not fight a team saving itself', function (): void {
    $team = Team::create([
        'name' => 'A', 'season_id' => $this->season->id,
        'league_id' => ($this->leagueIn)('MEN')->id, 'club_id' => $this->ourClub->id,
    ]);

    $team->final_position = '3';
    $team->save();

    expect($team->fresh()->final_position)->toBe('3');
});

it('refuses a rename onto a letter the category already uses, in the edit screen', function (): void {
    $admin = User::factory()->isAdmin()->create(['licence' => null]);

    $teamA = Team::create([
        'name' => 'A', 'season_id' => $this->season->id,
        'league_id' => ($this->leagueIn)('MEN', 'P3B')->id, 'club_id' => $this->ourClub->id,
    ]);
    $teamB = Team::create([
        'name' => 'B', 'season_id' => $this->season->id,
        'league_id' => ($this->leagueIn)('MEN', 'P4A')->id, 'club_id' => $this->ourClub->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $teamB])
        ->set('name', 'A')
        ->call('save')
        ->assertHasErrors('name');

    expect($teamB->fresh()->name)->toBe('B')
        ->and($teamA->fresh()->name)->toBe('A');
});
