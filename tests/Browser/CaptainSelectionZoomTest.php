<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;

/**
 * The team zoom of the preparation widget runs entirely in Alpine — it dims
 * rows and keeps the query string in step without a server round trip. None of
 * that is observable from a Livewire test, so it is covered here.
 */
beforeEach(function (): void {
    $this->season = Season::factory()->create([
        'is_active' => true,
        'start_at' => now()->subMonths(4),
        'end_at' => now()->addMonths(6),
    ]);

    $this->ownClub = Club::factory()->ownClub()->create();
    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->admin = User::factory()->isAdmin()->create();

    $this->teams = collect(['Zulu', 'Yankee'])->map(function (string $name) use ($league): Team {
        $team = Team::factory()->create([
            'name' => $name,
            'season_id' => $this->season->id,
            'league_id' => $league->id,
            'club_id' => $this->ownClub->id,
            'captain_id' => $this->admin->id,
        ]);

        $team->users()->attach(User::factory()->isCompetitor()->count(4)->create()->pluck('id'));

        Interclub::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $league->id,
            'visited_team_id' => $team->id,
            'visiting_team_id' => null,
            'week_number' => 3,
            'total_players' => 4,
            'start_date_time' => now()->addDays(20),
        ]);

        return $team;
    });
});

it('keeps every team chip reachable while a team is zoomed', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.interclubs.captain-selection'))
        ->assertSee('Zulu')
        ->assertSee('Yankee')
        ->click('Zulu')
        // The other chip used to disappear, stranding the user until they
        // cleared the zoom through "Tous".
        ->assertSee('Yankee')
        ->click('Yankee')
        ->assertSee('Zulu');
});

it('records the zoomed team in the query string', function (): void {
    $this->actingAs($this->admin);

    $zulu = $this->teams->firstWhere('name', 'Zulu');

    visit(route('admin.interclubs.captain-selection'))
        ->assertQueryStringMissing('zoomedTeamId')
        ->click('Zulu')
        ->assertQueryStringHas('zoomedTeamId', (string) $zulu->id)
        ->click('Tous')
        ->assertQueryStringMissing('zoomedTeamId');
});

it('restores the zoom from the query string on reload', function (): void {
    $this->actingAs($this->admin);

    $zulu = $this->teams->firstWhere('name', 'Zulu');

    visit(route('admin.interclubs.captain-selection') . '?zoomedTeamId=' . $zulu->id)
        ->assertSee('Zulu')
        ->assertSee('Yankee')
        ->assertNoJavascriptErrors();
});
