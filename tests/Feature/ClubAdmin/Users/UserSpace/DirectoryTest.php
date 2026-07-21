<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

const DIRECTORY_COMPONENT = 'pages::club-admin.users.user-space.directory';

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

it('lists active members of the current season', function (): void {
    $viewer = activeMember($this->season);
    $member = activeMember($this->season, ['first_name' => 'Camille', 'last_name' => 'Dupont']);
    $inactive = User::factory()->create(['first_name' => 'Ghost', 'last_name' => 'Inactive']);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertOk()
        ->assertSee('Camille')
        ->assertSee('Dupont')
        ->assertDontSee('Ghost');
});

it('shows a members ranking', function (): void {
    $viewer = activeMember($this->season);
    activeMember($this->season, ['first_name' => 'Rank', 'last_name' => 'Holder', 'ranking' => 'C4']);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSee('C4');
});

it('hides an unshared phone number from another member', function (): void {
    $viewer = activeMember($this->season);
    activeMember($this->season, ['phone_number' => '0470111222']); // shares nothing

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertDontSee('0470111222');
});

it('shows a phone number the member has opted to share', function (): void {
    $viewer = activeMember($this->season);
    activeMember($this->season, [
        'phone_number' => '0470333444',
        'contact_visibility' => ['phone' => true],
    ]);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSee('0470333444');
});

it('shows unshared contact details to a committee member', function (): void {
    $committee = activeMember($this->season);
    $committee->assignRole(Role::COMMITTEE->value);
    activeMember($this->season, ['phone_number' => '0470555666']); // shares nothing

    Livewire::actingAs($committee)
        ->test(DIRECTORY_COMPONENT, ['user' => $committee])
        ->assertSee('0470555666');
});

it('shows the team category alongside the team name', function (): void {
    $viewer = activeMember($this->season);
    $member = activeMember($this->season, ['first_name' => 'Team', 'last_name' => 'Player']);

    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'VETERANS']);
    $team = Team::factory()->create(['season_id' => $this->season->id, 'league_id' => $league->id, 'name' => 'C']);
    $team->users()->attach($member->id);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSee('C')
        ->assertSee(__('Veterans'));
});

it('defaults the season filter to the current season', function (): void {
    $viewer = activeMember($this->season);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSet('seasonFilter', $this->season->id);
});

it('filters members by team', function (): void {
    $viewer = activeMember($this->season);
    $inTeam = activeMember($this->season, ['first_name' => 'Teamed', 'last_name' => 'Up']);
    activeMember($this->season, ['first_name' => 'Solo', 'last_name' => 'Player']);

    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $team = Team::factory()->create(['season_id' => $this->season->id, 'league_id' => $league->id, 'name' => 'A']);
    $team->users()->attach($inTeam->id);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->set('teamFilter', $team->id)
        ->assertSee('Teamed')
        ->assertDontSee('Solo');
});

it('finds a member through compound-name search', function (): void {
    $viewer = activeMember($this->season);
    activeMember($this->season, ['first_name' => 'Jean-Pierre', 'last_name' => 'Van Oudenhove']);
    activeMember($this->season, ['first_name' => 'Alice', 'last_name' => 'Martin']);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->set('search', 'Jean Van')
        ->assertSee('Van Oudenhove')
        ->assertDontSee('Martin');
});
