<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * « À faire » sur l'écran des sélections (grilling 2026-10-25) : ce qui se
 * joue dans les 21 jours et demande un geste, y compris trouver des joueurs.
 */
beforeEach(function (): void {
    $this->freezeTime(fn (): null => null);
    $this->travelTo('2026-01-15 12:00:00');

    $season = Season::factory()->create(['is_active' => true, 'start_at' => '2025-09-01', 'end_at' => '2026-06-30']);

    $this->captain = User::factory()->isCompetitor()->create();

    $this->team = Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => League::factory()->create(['season_id' => $season->id, 'category' => 'MEN'])->id,
        'club_id' => Club::factory()->ownClub()->create()->id,
        'captain_id' => $this->captain->id,
    ]);
    $this->team->users()->attach(User::factory()->isCompetitor()->count(5)->create()->pluck('id'));
    $this->team->load('users');
});

/** A four-player fixture $days days out; members answer in team order. */
function todoFixture(int $days, int $available = 0, int $declined = 0): Interclub
{
    $team = test()->team;

    $interclub = Interclub::factory()->create([
        'season_id' => $team->season_id,
        'league_id' => $team->league_id,
        'visited_team_id' => $team->id,
        'visiting_team_id' => null,
        'total_players' => 4,
        'start_date_time' => now()->addDays($days)->setTime(19, 45),
    ]);

    foreach ($team->users->take($available) as $player) {
        $interclub->users()->attach($player->id, ['availability' => InterclubAvailability::AVAILABLE->value]);
    }

    foreach ($team->users->skip($available)->take($declined) as $player) {
        $interclub->users()->attach($player->id, ['availability' => InterclubAvailability::UNAVAILABLE->value]);
    }

    return $interclub;
}

function todoPage(): Testable
{
    return Livewire::actingAs(test()->captain)->test('pages::club-events.interclubs.captain-selection');
}

/** The opening tag of the row's primary action, from the rendered page. */
function todoPrimaryAction(Testable $page, Interclub $interclub): string
{
    $html = $page->html();
    $row = substr($html, strpos($html, 'wire:key="match-team-' . $interclub->id . '"'));
    $row = substr($row, 0, strpos($row, 'data-match-row') ?: strlen($row));

    preg_match('/<(?:button|a)[^>]*data-row-menu-primary[^>]*>/', $row, $match);

    return $match[0] ?? '';
}

it('keeps what is far off out of the to-do list, and puts a short team in it', function (): void {
    $farAndReady = todoFixture(days: 40, available: 5);
    $nearAndShort = todoFixture(days: 18, available: 2, declined: 3);

    $groups = todoPage()->viewData('matchGroups');

    expect(collect($groups['todo'])->pluck('id')->all())->toBe([$nearAndShort->id])
        ->and(collect($groups['upcoming'])->pluck('id')->all())->toBe([$farAndReady->id])
        ->and($groups['todo'][0]['status'])->toBe('missing_players');
});

it('says how many players are missing', function (): void {
    todoFixture(days: 18, available: 2, declined: 3);

    todoPage()
        ->assertSee(__('Missing players'))
        ->assertSee(trans_choice(':count player missing, everyone answered|:count players missing, everyone answered', 2, ['count' => 2]));
});

it('keeps saying it once the fixture turns urgent', function (): void {
    todoFixture(days: 10, available: 1);

    todoPage()
        ->assertSee(__('Needs attention'))
        ->assertSee(trans_choice(':count player missing, :pending without an answer|:count players missing, :pending without an answer', 3, ['count' => 3, 'pending' => 4]));
});

it('offers to remind the players while some have not answered', function (): void {
    $fixture = todoFixture(days: 18, available: 2);

    expect(todoPrimaryAction(todoPage(), $fixture))->toContain('confirmAvailabilityRequest(' . $fixture->id . ')');
});

it('offers to compose once everyone has answered', function (): void {
    $fixture = todoFixture(days: 18, available: 2, declined: 3);

    expect(todoPrimaryAction(todoPage(), $fixture))->toContain('openSelection(' . $fixture->id . ')');
});
