<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\InterclubAvailabilityRequestNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubLineupBroadcastNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubPlayerRemovedNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubSelectionNotification;
use App\Domains\Competitions\Interclub\Services\InterclubAvailabilityService;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
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

    $club = Club::factory()->ownClub()->create();

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
        fn ($notification): bool => $notification->captainMessage === $message,
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
        fn ($notification): bool => $notification->captainMessage === $message
            && $notification->selectedPlayers->contains('id', $this->player1->id),
    );
});

// ── notifySelectionChange ───────────────────────────────────────────────────

it('notifies a removed player even when the resulting selection is incomplete', function (): void {
    // total_players = 4, only player2 remains selected after removing player1 — incomplete.
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->users()->updateExistingPivot($this->player1->id, ['selection_confirmed_at' => now()]);
    $this->interclub->users()->updateExistingPivot($this->player2->id, ['selection_confirmed_at' => now()]);
    $this->interclub->deselect($this->player1);

    $this->service->notifySelectionChange($this->interclub, addedUserIds: [], removedUserIds: [$this->player1->id]);

    Notification::assertSentTo($this->player1, InterclubPlayerRemovedNotification::class);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player1->id,
        'selection_confirmed_at' => null,
    ]);
});

it('does not notify added players or broadcast the team when the selection is incomplete', function (): void {
    // total_players = 4, only 1 player selected after the change — incomplete.
    $this->interclub->select($this->player1);

    $this->service->notifySelectionChange($this->interclub, addedUserIds: [$this->player1->id], removedUserIds: []);

    Notification::assertNotSentTo($this->player1, InterclubSelectionNotification::class);
    Notification::assertNotSentTo($this->captain, InterclubLineupBroadcastNotification::class);
});

it('notifies added players and broadcasts an update when the selection becomes complete again', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->select($this->player3);
    $newPlayer = User::factory()->isCompetitor()->create();
    $this->team->users()->attach($newPlayer->id);
    $this->interclub->select($newPlayer);

    $this->service->notifySelectionChange($this->interclub, addedUserIds: [$newPlayer->id], removedUserIds: []);

    Notification::assertSentTo($newPlayer, InterclubSelectionNotification::class);
    Notification::assertSentTo(
        $this->captain,
        InterclubLineupBroadcastNotification::class,
        fn ($notification): bool => $notification->isUpdate === true,
    );

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $newPlayer->id,
    ]);
    $pivot = DB::table('interclub_user')
        ->where('interclub_id', $this->interclub->id)
        ->where('user_id', $newPlayer->id)
        ->first();
    expect($pivot->selection_confirmed_at)->not->toBeNull();
});

it('does not notify unchanged players when the selection is updated', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->select($this->player3);
    $newPlayer = User::factory()->isCompetitor()->create();
    $this->team->users()->attach($newPlayer->id);
    $this->interclub->select($newPlayer);

    $this->service->notifySelectionChange($this->interclub, addedUserIds: [$newPlayer->id], removedUserIds: []);

    Notification::assertNotSentTo($this->player1, InterclubSelectionNotification::class);
    Notification::assertNotSentTo($this->player2, InterclubSelectionNotification::class);
});

// ── mail rendering ───────────────────────────────────────────────────────────
// Notification::fake() does NOT render the mail, so these render the blade views directly.

it('renders the removed-player mail with the match details', function (): void {
    $html = (string) app(Markdown::class)->render('mail.interclub.removed', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
    ]);

    expect($html)
        ->toContain("n'êtes plus sélectionné")
        ->toContain('CTT Ottignies-Blocry A')
        ->toContain('ARC EN CIEL CTT F');
});

it('renders the lineup mail with an updated heading when isUpdate is true', function (): void {
    $html = (string) app(Markdown::class)->render('mail.interclub.lineup', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'selectedPlayers' => collect([$this->player1]),
        'category' => 'MEN',
        'captainMessage' => '',
        'isUpdate' => true,
    ]);

    expect($html)->toContain('mise à jour');
});

it('renders the lineup as a table ordered by force index rather than inline parentheses', function (): void {
    $this->player1->update(['first_name' => 'Alice', 'last_name' => 'Zulu', 'ranking' => 'B4', 'force_list' => 3]);
    $this->player2->update(['first_name' => 'Bob', 'last_name' => 'Alpha', 'ranking' => 'C2', 'force_list' => 12]);

    $html = (string) app(Markdown::class)->render('mail.interclub.selection', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'selectedPlayers' => collect([$this->player2, $this->player1]),
        'category' => 'MEN',
        'captainMessage' => '',
    ]);

    expect($html)
        ->toContain('Liste de force')
        ->toContain('#3')
        ->toContain('#12')
        ->toContain('B4')
        ->not->toContain('Alice Zulu (3)');

    // Lowest force index first: Alice (#3) is listed before Bob (#12).
    expect(strpos($html, 'Alice'))->toBeLessThan(strpos($html, 'Bob'));
});

it('marks the recipient in the selection lineup and leaves the others unmarked', function (): void {
    $this->player1->update(['first_name' => 'Alice', 'last_name' => 'Zulu', 'force_list' => 3]);
    $this->player2->update(['first_name' => 'Bob', 'last_name' => 'Alpha', 'force_list' => 12]);

    $html = (string) app(Markdown::class)->render('mail.interclub.selection', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'selectedPlayers' => collect([$this->player1, $this->player2]),
        'category' => 'MEN',
        'captainMessage' => '',
    ]);

    expect(substr_count($html, '>vous<'))->toBe(1);
});

it('falls back to a dash and drops the force column when nobody is ranked in the category', function (): void {
    $this->player1->update(['force_list' => null, 'force_list_women' => null]);

    $html = (string) app(Markdown::class)->render('mail.interclub.selection', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'selectedPlayers' => collect([$this->player1]),
        'category' => 'MEN',
        'captainMessage' => '',
    ]);

    expect($html)
        ->toContain($this->player1->full_name)
        ->not->toContain('Liste de force');
});
