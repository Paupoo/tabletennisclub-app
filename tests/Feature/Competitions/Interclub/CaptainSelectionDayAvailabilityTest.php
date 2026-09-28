<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubDayAvailabilityService;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * L'accordéon « Disponibilités de la journée » de l'écran des sélections : la
 * vue du sélectionneur qui doit débloquer une équipe. Les règles vivent dans
 * {@see InterclubDayAvailabilityService} ; ici, seulement qui le voit, et où il
 * mène.
 */
beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $ownClub = Club::factory()->ownClub()->create();

    $this->captain = User::factory()->isCompetitor()->create();
    $this->nearPlayer = User::factory()->isCompetitor()->create(['last_name' => 'Prochainematch']);
    $this->laterPlayer = User::factory()->isCompetitor()->create(['last_name' => 'Journeesuivante']);

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => $ownClub->id,
        'captain_id' => $this->captain->id,
        'name' => 'A',
    ]);
    $this->team->users()->attach([$this->captain->id, $this->nearPlayer->id, $this->laterPlayer->id]);

    $fixtureOn = fn (int $week, int $days): Interclub => Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'visited_team_id' => $this->team->id,
        'week_number' => $week,
        'total_players' => 4,
        'is_bye' => false,
        'start_date_time' => now()->addDays($days),
    ]);

    $this->nearFixture = $fixtureOn(42, 5);
    $this->laterFixture = $fixtureOn(43, 12);

    $this->nearFixture->markAvailability($this->nearPlayer, InterclubAvailability::AVAILABLE);
    $this->laterFixture->markAvailability($this->laterPlayer, InterclubAvailability::AVAILABLE);
});

/** Le fragment de la page qui appartient à l'accordéon, et à lui seul. */
function dayAvailabilityHtml($component): string
{
    $html = $component->html();
    $start = strpos($html, 'data-day-availability');

    expect($start)->not->toBeFalse();

    return substr($html, $start, strpos($html, 'data-day-availability-end') - $start);
}

it('shows the selector the day overview above the details', function (): void {
    Livewire::actingAs(User::factory()->isSelector()->create())
        ->test('pages::club-events.interclubs.captain-selection')
        ->assertSeeInOrder([__('Match day availability'), __('Details and selections')]);
});

it('leaves a plain captain with the list alone, without any section', function (): void {
    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->assertDontSee(__('Match day availability'))
        ->assertDontSee(__('Details and selections'));
});

it('lists the players of the day shown, and follows the day picked', function (): void {
    $component = Livewire::actingAs(User::factory()->isSelector()->create())
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('toggleDayAvailability');

    expect(dayAvailabilityHtml($component))
        ->toContain('Prochainematch')
        ->not->toContain('Journeesuivante');

    $component->call('selectDay', 43);

    expect(dayAvailabilityHtml($component))
        ->toContain('Journeesuivante')
        ->not->toContain('Prochainematch');
});

it('opens the lineup of a short team from its pill', function (): void {
    $component = Livewire::actingAs(User::factory()->isSelector()->create())
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('toggleDayAvailability');

    expect(dayAvailabilityHtml($component))->toContain('openSelection(' . $this->nearFixture->id . ')');
});

it('lets the committee only read the lineup behind a pill', function (): void {
    $component = Livewire::actingAs(User::factory()->isCommitteeMember()->create())
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('toggleDayAvailability');

    expect(dayAvailabilityHtml($component))
        ->toContain('inspectSelection(' . $this->nearFixture->id . ')')
        ->not->toContain('openSelection(');
});
