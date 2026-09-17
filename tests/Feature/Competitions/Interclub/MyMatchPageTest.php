<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->captain = User::factory()->isCompetitor()->create(['phone_number' => '0470 12 34 56']);
    $this->player = User::factory()->isCompetitor()->create();
    $this->stranger = User::factory()->isCompetitor()->create();

    $ownClub = Club::factory()->create(['is_own_club' => true]);
    $awayClub = Club::factory()->create(['is_own_club' => false]);

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $ownClub->id,
        'captain_id' => $this->captain->id,
    ]);
    $this->team->users()->attach([$this->player->id, $this->captain->id]);

    $this->opponent = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $awayClub->id,
    ]);
});

/** A home fixture our team plays, `$days` from now (negative = already played). */
function aMatch(int $days = 7): Interclub
{
    return Interclub::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'visited_team_id' => test()->team->id,
        'visiting_team_id' => test()->opponent->id,
        'start_date_time' => now()->addDays($days),
    ]);
}

it('opens the page for a player on the roster', function (): void {
    $match = aMatch();

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match])
        ->assertOk();
});

it('refuses a member who plays for neither side', function (): void {
    $match = aMatch();

    Livewire::actingAs($this->stranger)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match])
        ->assertForbidden();
});

it('refuses a bye', function (): void {
    $match = aMatch();
    $match->update(['is_bye' => true]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match])
        ->assertNotFound();
});

it('records an availability answer', function (): void {
    $match = aMatch();

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match])
        ->call('markAvailability', InterclubAvailability::AVAILABLE->value)
        ->assertHasNoErrors();

    expect($match->users()->where('users.id', $this->player->id)->first()?->registration->availability)
        ->toBe(InterclubAvailability::AVAILABLE->value);
});

it('locks the answer once the line-up is published', function (): void {
    $match = aMatch();
    $match->users()->attach($this->player->id, [
        'availability' => InterclubAvailability::AVAILABLE->value,
        'is_selected' => true,
        'selection_confirmed_at' => now(),
    ]);

    $component = Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match]);

    expect($component->viewData('canAnswer'))->toBeFalse();

    $component->call('markAvailability', InterclubAvailability::UNAVAILABLE->value);

    expect($match->users()->where('users.id', $this->player->id)->first()?->registration->availability)
        ->toBe(InterclubAvailability::AVAILABLE->value);
});

it('hides a draft line-up until the captain has sent it', function (): void {
    $match = aMatch();
    $match->users()->attach($this->player->id, ['is_selected' => true]);

    $component = Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match]);

    expect($component->viewData('lineupPublished'))->toBeFalse()
        ->and($component->viewData('isSelected'))->toBeFalse()
        ->and($component->viewData('canAnswer'))->toBeTrue();
});

it('tells a selected player they are playing', function (): void {
    $match = aMatch();
    $match->users()->attach($this->player->id, [
        'is_selected' => true,
        'selection_confirmed_at' => now(),
    ]);

    $component = Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match]);

    expect($component->viewData('isSelected'))->toBeTrue()
        ->and($component->viewData('lineup')->pluck('id')->all())->toContain($this->player->id);
});

it('shows the captain phone number so a selected player can withdraw', function (): void {
    $match = aMatch();
    $match->users()->attach($this->player->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match])
        ->assertSee('0470 12 34 56');
});

it('reads the score from interclub_results, our side first', function (): void {
    $match = aMatch(-7);
    InterclubResult::where('interclub_id', $match->id)->update([
        'score' => '12-4',
        'result' => InterclubResultEnum::WIN->value,
    ]);

    $component = Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match]);

    expect($component->viewData('score'))->toBe('12-4')
        ->and($component->viewData('resultLabel'))->toBe(__('Win'));
});

it('flips the score for the away side', function (): void {
    $match = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->opponent->id,
        'visiting_team_id' => $this->team->id,
        'start_date_time' => now()->subDays(7),
    ]);

    InterclubResult::where('interclub_id', $match->id)->update(['score' => '12-4']);

    $component = Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match]);

    expect($component->viewData('score'))->toBe('4-12')
        ->and($component->viewData('isHome'))->toBeFalse();
});

it('says a played match is still awaiting its result', function (): void {
    $match = aMatch(-7);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match])
        ->assertSee(__('Match played — result pending'));
});

it('downloads the fixture as a calendar file', function (): void {
    $match = aMatch();
    $match->update(['captain_message' => 'RDV 19h au club']);

    $response = $this->actingAs($this->player)
        ->get(route('admin.interclubs.my-match.ics', $match));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    expect($response->getContent())
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('RDV 19h au club')
        ->toContain('UID:interclub-' . $match->id . '@');
});

it('refuses the calendar file to a member who plays for neither side', function (): void {
    $match = aMatch();

    $this->actingAs($this->stranger)
        ->get(route('admin.interclubs.my-match.ics', $match))
        ->assertForbidden();
});

it('offers no calendar file for a match already played', function (): void {
    $upcoming = aMatch();
    $played = aMatch(-7);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $upcoming])
        ->assertSee(__('Add to my calendar'));

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $played])
        ->assertDontSee(__('Add to my calendar'));
});

it('does not say a past line-up is "not yet" published', function (): void {
    $match = aMatch(-7);

    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match])
        ->assertSee(__('No line-up was recorded for this match.'))
        ->assertDontSee(__('The captain has not published the line-up yet.'));
});

it('keeps a délégation out of the personal band but shows them the fixture', function (): void {
    $match = aMatch();
    $delegate = User::factory()->create();
    $delegate->givePermissionTo(Permission::InterclubsManage->value);

    $component = Livewire::actingAs($delegate)
        ->test('pages::club-events.interclubs.my-match', ['interclub' => $match]);

    $component->assertOk();

    expect($component->viewData('isOnRoster'))->toBeFalse()
        ->and($component->viewData('canAnswer'))->toBeFalse();
});

it('lists played matches on the personal match list, which only held future ones', function (): void {
    $upcoming = aMatch();
    $played = aMatch(-7);
    InterclubResult::where('interclub_id', $played->id)->update([
        'score' => '9-7',
        'result' => InterclubResultEnum::WIN->value,
    ]);

    $component = Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches');

    $played_ids = $component->viewData('played')->pluck('id')->all();

    expect($played_ids)->toBe([$played->id])
        ->and($played_ids)->not->toContain($upcoming->id)
        ->and($component->viewData('played')->first()['score'])->toBe('9-7');
});
