<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubAvailabilityService;
use App\Domains\Competitions\Interclub\Services\InterclubPreparationService;
use App\Domains\Shared\Enums\InterclubForfeit;
use App\Jobs\SendInterclubAvailabilityRequestJob;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

/**
 * A fixture the federation has cancelled by forfeit stays on every screen,
 * marked, so a member who wrote the date down learns why it is off rather than
 * seeing it vanish. It asks nobody for anything; what members said about it
 * stays dormant in case the federation retracts.
 */
uses(CreateUser::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->ownClub = Club::factory()->create(['is_own_club' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->player = User::factory()->isCompetitor()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $this->ownClub->id,
    ]);
    $this->team->users()->attach($this->player->id);

    $this->cancelled = Interclub::factory()->create([
        'aftt_match_id' => 'PBBWH03/076',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(3),
        'week_number' => now()->addDays(3)->isoWeek,
        'is_bye' => false,
        'forfeit' => InterclubForfeit::OPPONENT_FORFEIT,
    ]);
});

it('never rates a forfeited fixture as needing attention', function (): void {
    expect(app(InterclubPreparationService::class)->fixtureStatus($this->cancelled->fresh()))->toBe('forfeit');
});

it('never asks the team whether they are free for a forfeited fixture', function (): void {
    Queue::fake();

    app(InterclubAvailabilityService::class)->requestAvailability($this->cancelled);

    Queue::assertNotPushed(SendInterclubAvailabilityRequestJob::class);
});

it('refuses to compose a lineup for a forfeited fixture', function (): void {
    $this->team->update(['captain_id' => $this->player->id]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->cancelled->id)
        ->assertForbidden();
});

it('leaves a forfeited fixture out of a member’s bulk answer', function (): void {
    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->call('bulkMarkAvailability', 'available');

    $this->assertDatabaseMissing('interclub_user', [
        'interclub_id' => $this->cancelled->id,
        'user_id' => $this->player->id,
    ]);
});

it('takes no availability answer on a forfeited fixture', function (): void {
    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->call('markAvailability', $this->cancelled->id, 'available');

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $this->cancelled])
        ->call('markAvailability', 'available');

    $this->assertDatabaseMissing('interclub_user', [
        'interclub_id' => $this->cancelled->id,
        'user_id' => $this->player->id,
    ]);
});

it('keeps a forfeited fixture in the calendar feed, cancelled and saying so', function (): void {
    $event = app(UserCalendarService::class)
        ->eventsFor($this->player, showAllEvents: true)
        ->where('type', 'interclub')
        ->first();

    expect($event['sourceId'])->toBe($this->cancelled->id)
        ->and($event['isCancelled'])->toBeTrue()
        ->and($event['title'])->toStartWith(__('CANCELLED (forfeit)') . ' – ');
});

it('marks the forfeit on a member’s own match list', function (): void {
    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->assertSee(__('Opponent forfeit'));
});

it('marks the forfeit on the match page', function (): void {
    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $this->cancelled])
        ->assertSee(__('Opponent forfeit'));
});

it('marks the forfeit on the schedule screen', function (): void {
    Livewire::actingAs($this->createFakeAdmin())
        ->test('pages::club-events.interclubs.interclubs')
        ->assertSee(__('Opponent forfeit'));
});

it('marks the forfeit on the captain’s screen, with nothing to do', function (): void {
    $this->team->update(['captain_id' => $this->player->id]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.captain-selection')
        ->assertSee(__('Opponent forfeit'))
        ->assertDontSeeHtml('openSelection(' . $this->cancelled->id . ')')
        ->assertDontSeeHtml('confirmAvailabilityRequest(' . $this->cancelled->id . ')');
});
