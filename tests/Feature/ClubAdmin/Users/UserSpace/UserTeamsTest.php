<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\InterclubResultEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

it('aggregates the roster availabilities on each upcoming match', function (): void {
    $interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(7),
    ]);

    $interclub->markAvailability($this->captain, InterclubAvailability::AVAILABLE);

    Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSee(trans_choice(':count available|:count available', 1))
        ->assertSee(trans_choice(':count without response|:count without response', 1));
});

it('lists the roster by force list, then surname, then first name, with each record', function (): void {
    $this->team->users()->detach();
    $zeller = User::factory()->isCompetitor()->create(['first_name' => 'Luc', 'last_name' => 'Zeller', 'force_list' => 2, 'ranking' => 'C0']);
    $bruno = User::factory()->isCompetitor()->create(['first_name' => 'Bruno', 'last_name' => 'Martin', 'force_list' => 4]);
    $alice = User::factory()->isCompetitor()->create(['first_name' => 'Alice', 'last_name' => 'Martin', 'force_list' => 4]);
    $unlisted = User::factory()->isCompetitor()->create(['first_name' => 'Yann', 'last_name' => 'Abel', 'force_list' => null]);
    $this->team->users()->attach([$zeller->id, $bruno->id, $alice->id, $unlisted->id, $this->member->id]);
    $this->member->update(['last_name' => 'Member']);
    // The force list is recomputed from rankings on save: pin it underneath.
    foreach ([$zeller->id => 2, $bruno->id => 4, $alice->id => 4, $unlisted->id => null, $this->member->id => 9] as $id => $position) {
        User::query()->whereKey($id)->toBase()->update(['force_list' => $position]);
    }

    $played = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->subDays(7),
    ]);
    foreach ([true, true, false, true] as $position => $won) {
        InterclubIndividualMatch::factory()->for($played)->create(['position' => $position + 1, 'user_id' => $zeller->id, 'we_won' => $won]);
    }

    $html = Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSeeHtmlInOrder(['data-roster-player="' . $zeller->id . '"', 'data-roster-player="' . $alice->id . '"', 'data-roster-player="' . $bruno->id . '"', 'data-roster-player="' . $this->member->id . '"', 'data-roster-player="' . $unlisted->id . '"'])
        ->html();

    $zellerRow = Str::between($html, 'data-roster-player="' . $zeller->id . '"', 'data-roster-player="' . $alice->id . '"');

    expect($zellerRow)->toContain('#2')->toContain('Zeller')->toContain('C0')->toContain('3-1')
        ->and(Str::between($html, 'data-roster-player="' . $unlisted->id . '"', '</div>'))->toContain('—');
});

it('lists the results newest first, our score first, and who played with their record', function (): void {
    $this->member->update(['last_name' => 'Firstlisted']);
    $this->captain->update(['last_name' => 'Secondlisted']);
    User::query()->whereKey($this->member->id)->toBase()->update(['force_list' => 1]);
    User::query()->whereKey($this->captain->id)->toBase()->update(['force_list' => 2]);
    $away = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visiting_team_id' => $this->team->id,
        'start_date_time' => now()->subDays(7),
        'is_bye' => false,
    ]);
    $older = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->subDays(14),
        'is_bye' => false,
    ]);
    InterclubResult::query()->updateOrCreate(['interclub_id' => $away->id], ['team_id' => $this->team->id, 'season_id' => $this->season->id, 'score' => '12-4', 'result' => InterclubResultEnum::LOSS]);
    InterclubResult::query()->updateOrCreate(['interclub_id' => $older->id], ['team_id' => $this->team->id, 'season_id' => $this->season->id, 'score' => '10-6', 'result' => InterclubResultEnum::WIN]);
    InterclubIndividualMatch::factory()->for($away)->create(['position' => 1, 'user_id' => $this->captain->id, 'we_won' => true]);
    InterclubIndividualMatch::factory()->for($away)->create(['position' => 2, 'user_id' => $this->member->id, 'we_won' => false]);
    InterclubIndividualMatch::factory()->for($away)->create(['position' => 3, 'user_id' => $this->member->id, 'we_won' => false]);
    InterclubIndividualMatch::factory()->for($away)->create(['position' => 4, 'user_id' => null, 'our_player_name' => 'Guest Player', 'our_player_licence' => '123456', 'we_won' => true]);
    InterclubIndividualMatch::factory()->for($away)->double()->create(['we_won' => true]);

    $html = Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSee(__('Results'))
        ->assertSeeHtmlInOrder(['data-result="' . $away->id . '"', 'data-result="' . $older->id . '"'])
        ->html();

    $awayRow = Str::between($html, 'data-result="' . $away->id . '"', 'data-result="' . $older->id . '"');

    expect($html)->toContain(route('admin.interclubs.my-match', $away->id))
        ->and($awayRow)->toContain('4-12')
        ->toMatch('/Firstlisted.*0-2.*Secondlisted.*1-0.*Guest Player.*1-0/s');
});

it('shows two upcoming matches and five results, the rest behind a button', function (): void {
    foreach (range(1, 3) as $week) {
        Interclub::factory()->create(['season_id' => $this->season->id, 'league_id' => $this->league->id, 'visited_team_id' => $this->team->id, 'start_date_time' => now()->addWeeks($week)]);
    }
    foreach (range(1, 6) as $week) {
        Interclub::factory()->create(['season_id' => $this->season->id, 'league_id' => $this->league->id, 'visited_team_id' => $this->team->id, 'start_date_time' => now()->subWeeks($week), 'is_bye' => false]);
    }

    Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSee(__('Show :count more', ['count' => 1]))
        ->assertSee(__('Result pending'));
});

it('tells two players sharing a surname apart by their initial', function (): void {
    $alice = User::factory()->isCompetitor()->create(['first_name' => 'Alice', 'last_name' => 'Martin']);
    $bruno = User::factory()->isCompetitor()->create(['first_name' => 'Bruno', 'last_name' => 'Martin']);
    $this->team->users()->attach([$alice->id, $bruno->id]);
    $played = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->subDays(7),
        'is_bye' => false,
    ]);
    InterclubIndividualMatch::factory()->for($played)->create(['position' => 1, 'user_id' => $alice->id, 'we_won' => true]);
    InterclubIndividualMatch::factory()->for($played)->create(['position' => 2, 'user_id' => $bruno->id, 'we_won' => false]);
    InterclubIndividualMatch::factory()->for($played)->create(['position' => 3, 'user_id' => $this->member->id, 'we_won' => true]);

    Livewire::actingAs($this->member)
        ->test(USER_TEAMS_COMPONENT, ['user' => $this->member])
        ->assertSee('Martin A.')
        ->assertSee('Martin B.')
        ->assertDontSee($this->member->last_name . ' ' . mb_substr($this->member->first_name, 0, 1) . '.');
});
