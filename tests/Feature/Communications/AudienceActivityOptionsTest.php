<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Services\AudienceActivityOptions;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\AudienceActivityKind;
use App\Domains\Shared\Enums\LeagueCategory;

/*
| The teams table holds every team of the league, the opponents imported from
| the federation included. Aiming a message at a team only makes sense for our
| own teams of the season being played, and "Team A" alone does not say
| whether it is the men's, the women's or the veterans' A.
*/

it('offers our own teams of the active season, named with their category and division', function (): void {
    $ownClub = Club::factory()->ownClub()->create();
    $opponent = Club::factory()->create();
    $current = Season::factory()->create(['is_active' => true]);
    $previous = Season::factory()->create(['is_active' => false]);
    $veterans = League::factory()->create(['category' => LeagueCategory::VETERANS->name, 'division' => '3B']);
    $women = League::factory()->create(['category' => LeagueCategory::WOMEN->name, 'division' => '2A']);

    $ours = Team::factory()->create(['name' => 'A', 'club_id' => $ownClub->id, 'season_id' => $current->id, 'league_id' => $veterans->id]);
    $oursToo = Team::factory()->create(['name' => 'B', 'club_id' => $ownClub->id, 'season_id' => $current->id, 'league_id' => $women->id]);
    Team::factory()->create(['name' => 'C', 'club_id' => $opponent->id, 'season_id' => $current->id, 'league_id' => $veterans->id]);
    Team::factory()->create(['name' => 'D', 'club_id' => $ownClub->id, 'season_id' => $previous->id, 'league_id' => $veterans->id]);

    expect(app(AudienceActivityOptions::class)->for(AudienceActivityKind::Team))->toBe([
        ['id' => $ours->id, 'name' => __('Team :name', ['name' => 'A']) . ' — ' . LeagueCategory::VETERANS->label() . ' · 3B'],
        ['id' => $oursToo->id, 'name' => __('Team :name', ['name' => 'B']) . ' — ' . LeagueCategory::WOMEN->label() . ' · 2A'],
    ]);
});
