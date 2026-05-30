<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\InterclubAvailability;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Domains\Competitions\Interclub\Notifications\InterclubAvailabilityRequestNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubLineupBroadcastNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubSelectionNotification;
use App\Services\InterclubAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();

    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->captain = User::factory()->isCompetitor()->create();
    $this->player1 = User::factory()->isCompetitor()->create();
    $this->player2 = User::factory()->isCompetitor()->create();
    $this->player3 = User::factory()->isCompetitor()->create();

    $club = Club::factory()->create(['licence' => config('app.club_licence')]);

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $club->id,
    ]);

    $this->team->users()->attach([$this->captain->id, $this->player1->id, $this->player2->id, $this->player3->id]);

    $this->interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);

    $this->service = app(InterclubAvailabilityService::class);
});

it('sends selection notification to each selected player', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);

    $this->service->confirmSelection($this->interclub, 'Départ à 18h45.');

    Notification::assertSentTo($this->player1, InterclubSelectionNotification::class);
    Notification::assertSentTo($this->player2, InterclubSelectionNotification::class);
    Notification::assertNotSentTo($this->player3, InterclubSelectionNotification::class);
});

it('selection notification carries the captain message', function (): void {
    $this->interclub->select($this->player1);
    $message = 'Rendez-vous à 18h30 au club.';

    $this->service->confirmSelection($this->interclub, $message);

    Notification::assertSentTo(
        $this->player1,
        InterclubSelectionNotification::class,
        fn ($notification) => $notification->captainMessage === $message,
    );
});

it('sets selection_confirmed_at on pivot after confirmation', function (): void {
    $this->interclub->select($this->player1);

    $this->service->confirmSelection($this->interclub);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player1->id,
        'is_selected' => true,
    ]);

    $pivot = DB::table('interclub_user')
        ->where('interclub_id', $this->interclub->id)
        ->where('user_id', $this->player1->id)
        ->first();

    expect($pivot->selection_confirmed_at)->not->toBeNull();
});

it('sends availability request only to players who have not responded', function (): void {
    $responded = User::factory()->isCompetitor()->create();
    $this->team->users()->syncWithoutDetaching([$responded->id]);
    $this->interclub->markAvailability($responded, InterclubAvailability::AVAILABLE);

    $this->service->requestAvailability($this->interclub);

    Notification::assertNotSentTo($responded, InterclubAvailabilityRequestNotification::class);
    Notification::assertSentTo($this->player1, InterclubAvailabilityRequestNotification::class);
    Notification::assertSentTo($this->player2, InterclubAvailabilityRequestNotification::class);
});

it('sends broadcast notification to non-selected team members', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);

    $this->service->confirmSelection($this->interclub, 'Rendez-vous à 18h30.');

    Notification::assertSentTo($this->captain, InterclubLineupBroadcastNotification::class);
    Notification::assertSentTo($this->player3, InterclubLineupBroadcastNotification::class);
});

it('selected players do not receive the broadcast notification', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);

    $this->service->confirmSelection($this->interclub);

    Notification::assertNotSentTo($this->player1, InterclubLineupBroadcastNotification::class);
    Notification::assertNotSentTo($this->player2, InterclubLineupBroadcastNotification::class);
});

it('non-selected players do not receive the selection notification', function (): void {
    $this->interclub->select($this->player1);

    $this->service->confirmSelection($this->interclub);

    Notification::assertNotSentTo($this->player2, InterclubSelectionNotification::class);
    Notification::assertNotSentTo($this->player3, InterclubSelectionNotification::class);
});

it('broadcast notification carries the captain message and selected lineup', function (): void {
    $this->interclub->select($this->player1);
    $message = 'Départ depuis le club à 18h.';

    $this->service->confirmSelection($this->interclub, $message);

    Notification::assertSentTo(
        $this->player3,
        InterclubLineupBroadcastNotification::class,
        fn ($notification) => $notification->captainMessage === $message
            && $notification->selectedPlayers->contains('id', $this->player1->id),
    );
});
