<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\InterclubAvailabilityRequestNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubSelectionNotification;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\NewTournamentPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets the member toggle a notification preference from the settings page', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.settings', ['user' => $user])
        ->assertSee(__('New tournaments and events'))
        ->set('notifyNewTournaments', false);

    expect($user->fresh()->wantsNotification('new_tournaments'))->toBeFalse()
        ->and($user->fresh()->wantsNotification('availability_requests'))->toBeTrue();
});

it('defaults every optional notification to enabled', function (): void {
    $user = User::factory()->create();

    expect($user->wantsNotification('new_tournaments'))->toBeTrue()
        ->and($user->wantsNotification('availability_requests'))->toBeTrue()
        ->and($user->wantsNotification('interclub_selections'))->toBeTrue()
        ->and($user->wantsNotification('unknown_future_key'))->toBeTrue();
});

it('mutes the new-tournament notification for members who opted out', function (): void {
    makeActiveSeason();
    $tournament = Tournament::factory()->create();

    $optedOut = User::factory()->create([
        'notification_preferences' => ['new_tournaments' => false],
    ]);
    $default = User::factory()->create();

    $notification = new NewTournamentPublishedNotification($tournament, $optedOut);

    expect($notification->via($optedOut))->toBe([])
        ->and($notification->via($default))->toBe(['mail', 'database']);
});

it('actually skips delivery when the member muted the family', function (): void {
    $season = makeActiveSeason();
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $team = Team::factory()->create(['season_id' => $season->id, 'league_id' => $league->id]);
    $interclub = Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'start_date_time' => now()->addDays(7),
    ]);

    $muted = User::factory()->create([
        'notification_preferences' => ['availability_requests' => false],
    ]);
    $listening = User::factory()->create();

    $muted->notify(new InterclubAvailabilityRequestNotification($interclub));
    $listening->notify(new InterclubAvailabilityRequestNotification($interclub));

    expect($muted->notifications()->count())->toBe(0)
        ->and($listening->notifications()->count())->toBe(1);
});

it('never mutes a selection for a member who kept the default preferences', function (): void {
    $season = makeActiveSeason();
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $team = Team::factory()->create(['season_id' => $season->id, 'league_id' => $league->id]);
    $interclub = Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'start_date_time' => now()->addDays(7),
    ]);
    $user = User::factory()->create();

    expect((new InterclubSelectionNotification($interclub, ''))->via($user))
        ->toBe(['mail', 'database']);
});
