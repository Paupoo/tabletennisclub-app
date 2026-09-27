<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

/*
| The team picker of the members list — both the filter and "add to a team" —
| listed every row of the teams table: the opponents imported from the
| federation and the teams of past seasons, a hundred "Team A" with nothing to
| tell them apart. Adding a member to one of those was one click away. It now
| offers our own teams of the active season, with their category.
*/

it('offers only our own teams of the active season, with their category', function (): void {
    $ownClub = Club::factory()->ownClub()->create();
    $opponent = Club::factory()->create();
    $current = makeActiveSeason();
    // Every season dated by hand, the league's included: the factory draws a
    // random year, and two draws in eleven overlap the calendar year that
    // makeActiveSeason() takes.
    $previous = Season::factory()->create([
        'is_active' => false,
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);
    $veterans = League::factory()->create(['category' => LeagueCategory::VETERANS->name, 'season_id' => $current->id]);

    $ours = Team::factory()->create(['name' => 'A', 'club_id' => $ownClub->id, 'season_id' => $current->id, 'league_id' => $veterans->id]);
    Team::factory()->create(['name' => 'B', 'club_id' => $opponent->id, 'season_id' => $current->id, 'league_id' => $veterans->id]);
    Team::factory()->create(['name' => 'C', 'club_id' => $ownClub->id, 'season_id' => $previous->id, 'league_id' => $veterans->id]);

    $teams = Livewire::actingAs($this->createFakeAdmin())
        ->test('pages::club-admin.users.index')
        ->instance()
        ->teams;

    expect($teams->pluck('id')->all())->toBe([$ours->id])
        ->and($teams->first()['name'])->toBe(__('Team') . ' A · ' . LeagueCategory::VETERANS->label());
});
