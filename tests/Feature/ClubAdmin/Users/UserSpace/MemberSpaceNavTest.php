<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the member space tabs on every personal page', function (string $component): void {
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test($component, ['user' => $user])
        ->assertSee(__('My Calendar'))
        ->assertSee(__('My registrations'))
        ->assertSee(__('Settings'));
})->with([
    'pages::club-admin.users.user-space.profile',
    'pages::club-admin.users.user-space.user-teams',
    'pages::club-admin.users.user-space.calendar',
    'pages::club-admin.users.user-space.settings',
]);

it('hides the matches tab for non-competitors', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->assertDontSee(__('My matches'));
});

it('counts the availabilities still to give on the matches tab', function (): void {
    $season = makeActiveSeason();
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $user = User::factory()->isCompetitor()->create();

    $team = Team::factory()->create(['season_id' => $season->id, 'league_id' => $league->id]);
    $team->users()->attach($user->id);

    Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'start_date_time' => now()->addDays(5),
    ]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->assertSee(__('My matches'))
        ->assertSeeHtml('font-bold text-white">1<');
});

it('shows the real affiliation status in the identity strip', function (): void {
    $season = makeActiveSeason();
    $user = activeMember($season);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.calendar', ['user' => $user])
        ->assertSee(__('Affiliated · season :season', ['season' => $season->name]));
});

it('sequences the affiliation flow: formula, then trainings, then summary and submit', function (): void {
    Season::factory()->create([
        'is_active' => true,
        'registrations_open' => true,
        'start_at' => now()->startOfYear(),
        'end_at' => now()->endOfYear(),
    ]);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSeeInOrder([
            __('Competition'),
            __('Training'),
            __('Summary and submit'),
            __('Submit my registration'),
        ]);
});
