<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const CALENDAR_COMPONENT = 'pages::club-admin.users.user-space.calendar';

it('shows a chip per selected category and removes it individually', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(CALENDAR_COMPONENT, ['user' => $user])
        ->set('selectedCategories', ['tournament', 'meeting'])
        ->assertSee(__('Tournament'))
        ->assertSee(__('Meeting'));

    $component->call('removeFilter', 'category:tournament');

    expect($component->get('selectedCategories'))->toBe(['meeting']);

    $component->call('clearFilters');

    expect($component->get('selectedCategories'))->toBe([]);
});

it('keeps the view mode segmented control out of the filter chips', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CALENDAR_COMPONENT, ['user' => $user])
        ->set('showAllEvents', true)
        ->assertSee(__('All club events'));

    // le mode de vue ne produit aucun chip de filtre
    $component = Livewire::actingAs($user)->test(CALENDAR_COMPONENT, ['user' => $user]);
    $component->set('showAllEvents', true);
    expect($component->instance()->getFilterChips())->toBe([]);
});

/*
| « Tout le club » montre les rencontres des autres équipes : leur tuile mène à
| la page du match, que tout membre peut lire depuis le 2026-09-24.
*/
it('links every club match tile to its match page, not only the member own', function (): void {
    $season = makeActiveSeason();
    $user = User::factory()->create();

    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $ownClub = Club::factory()->ownClub()->create();
    $team = Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $ownClub->id,
        'captain_id' => User::factory()->create()->id,
    ]);

    $match = Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'is_bye' => false,
        'start_date_time' => now()->addDays(2)->setTime(19, 45),
    ]);

    Livewire::actingAs($user)
        ->test(CALENDAR_COMPONENT, ['user' => $user])
        ->set('showAllEvents', true)
        ->call('selectDay', $match->start_date_time->toDateString())
        ->assertSeeHtml(route('admin.interclubs.my-match', $match));
});
