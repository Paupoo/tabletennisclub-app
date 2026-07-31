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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Characterisation test for the captain-selection preparation widget.
 *
 * The scenario below is built so every branch of the status rule is exercised
 * at least once, and so the two aggregation quirks are pinned down explicitly:
 * a fully played week scores as ready (week 1), and `future` outranks
 * `actionable` when both appear in the same week (week 3).
 *
 * The whole point is to make "same output, fewer queries" verifiable rather
 * than asserted, so these expectations must survive the single-pass rewrite
 * untouched. They only change in the commit that deliberately changes them.
 */
beforeEach(function (): void {
    $this->freezeTime(fn () => null);
    $this->travelTo('2026-01-15 12:00:00');

    $this->season = Season::factory()->create([
        'is_active' => true,
        'start_at' => '2025-09-01',
        'end_at' => '2026-06-30',
    ]);

    $this->ownClub = Club::factory()->ownClub()->create();
    $this->league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
    ]);

    $this->admin = User::factory()->isAdmin()->create();

    $this->teams = collect(['A', 'B', 'C'])->mapWithKeys(function (string $name): array {
        $team = Team::factory()->create([
            'name' => $name,
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'club_id' => $this->ownClub->id,
            'captain_id' => $this->admin->id,
        ]);

        $team->users()->attach(
            User::factory()->isCompetitor()->count(4)->create()->pluck('id')
        );

        return [$name => $team->fresh('users')];
    });
});

/** Creates a fixture for the given team, at a fixed offset from the frozen now. */
function scheduleMatch(Team $team, int $week, string $when): Interclub
{
    return Interclub::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'visited_team_id' => $team->id,
        'visiting_team_id' => null,
        'week_number' => $week,
        'total_players' => 4,
        'start_date_time' => $when,
    ]);
}

/** Marks the first $count team members available for the fixture. */
function markAvailable(Interclub $interclub, Team $team, int $count): void
{
    foreach ($team->users->take($count) as $player) {
        $interclub->users()->attach($player->id, [
            'availability' => InterclubAvailability::AVAILABLE->value,
        ]);
    }
}

/** Selects the first $count team members, optionally confirming the lineup. */
function selectPlayers(Interclub $interclub, Team $team, int $count, bool $confirmed = false): void
{
    foreach ($team->users->take($count) as $player) {
        $interclub->users()->attach($player->id, [
            'availability' => InterclubAvailability::AVAILABLE->value,
            'is_selected' => true,
            'selection_confirmed_at' => $confirmed ? now() : null,
        ]);
    }
}

/** Builds the four-week scenario the expectations below are written against. */
function buildScenario(): void
{
    [$a, $b, $c] = [test()->teams['A'], test()->teams['B'], test()->teams['C']];

    // Week 1 — already played by every team.
    foreach ([$a, $b, $c] as $team) {
        scheduleMatch($team, 1, '2026-01-08 19:45:00');
    }

    // Week 2 — three days out, one of each live status.
    selectPlayers(scheduleMatch($a, 2, '2026-01-18 19:45:00'), $a, 4, confirmed: true);
    scheduleMatch($b, 2, '2026-01-18 19:45:00');
    markAvailable(scheduleMatch($c, 2, '2026-01-18 19:45:00'), $c, 4);

    // Week 3 — thirty days out, actionable meets future, team C does not play.
    markAvailable(scheduleMatch($a, 3, '2026-02-14 19:45:00'), $a, 4);
    scheduleMatch($b, 3, '2026-02-14 19:45:00');

    // Week 4 — sixty days out, nothing done anywhere.
    foreach ([$a, $b, $c] as $team) {
        scheduleMatch($team, 4, '2026-03-16 19:45:00');
    }
}

/** @return array<string, mixed> */
function summaryFor(User $user): array
{
    return Livewire::actingAs($user)
        ->test('pages::club-events.interclubs.captain-selection')
        ->viewData('weekSummary');
}

it('scores the preparation widget week by week', function (): void {
    buildScenario();

    $summary = summaryFor($this->admin);

    expect(collect($summary['weeks'])->pluck('status', 'wk')->all())->toBe([
        // Every match is played, so nothing is left to flag: the week reads as ready.
        1 => 'confirmed',
        // Team B has done nothing three days out.
        2 => 'urgent',
        // Team A is composable now, but team B's distant match outranks it.
        3 => 'future',
        4 => 'future',
    ]);

    expect($summary['total'])->toBe(4)
        ->and($summary['ok'])->toBe(1)
        ->and($summary['preparation_score'])->toBe(25);
});

it('colours each cell of the team by week matrix', function (): void {
    buildScenario();

    $summary = summaryFor($this->admin);
    $id = fn (string $name): int => $this->teams[$name]->id;

    expect($summary['matrix'][$id('A')])->toBe([
        1 => 'confirmed',
        2 => 'confirmed',
        3 => 'actionable',
        4 => 'future',
    ]);

    expect($summary['matrix'][$id('B')])->toBe([
        1 => 'confirmed',
        2 => 'urgent',
        3 => 'future',
        4 => 'future',
    ]);

    expect($summary['matrix'][$id('C')])->toBe([
        1 => 'confirmed',
        2 => 'actionable',
        // No fixture that week — the cell stays empty.
        3 => null,
        4 => 'future',
    ]);
});

it('lists every own-club team in the summary', function (): void {
    buildScenario();

    expect(collect(summaryFor($this->admin)['teams'])->pluck('name')->all())
        ->toBe(['A', 'B', 'C']);
});

it('reports a past fixture as past on the team cards', function (): void {
    buildScenario();

    $teamsData = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.captain-selection')
        ->viewData('teamsData');

    $teamA = collect($teamsData)->firstWhere('id', $this->teams['A']->id);

    expect(collect($teamA['matches'])->pluck('status', 'wk')->all())->toBe([
        1 => 'past',
        2 => 'confirmed',
        3 => 'actionable',
        4 => 'future',
    ]);

    $week2 = collect($teamA['matches'])->firstWhere('wk', 2);

    expect($week2['selected_count'])->toBe(4)
        ->and($week2['available_count'])->toBe(4)
        ->and($week2['pending_count'])->toBe(0)
        ->and($week2['max_players'])->toBe(4);
});

/**
 * Query budget. The widget used to issue one lookup per cell plus a relation
 * load on top, so a nine-team season ran past a thousand queries per render —
 * including on every keystroke inside the selection drawer. These ceilings are
 * deliberately loose: they exist to catch a reintroduced N+1, not to police
 * every added query.
 */
it('renders the page within its query budget', function (): void {
    buildScenario();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.captain-selection');

    expect($queries)->toBeLessThan(30);
});

it('opens the selection drawer within its query budget', function (): void {
    buildScenario();

    $interclub = Interclub::where('week_number', 2)
        ->where('visited_team_id', $this->teams['A']->id)
        ->firstOrFail();

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.captain-selection');

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $component->call('openSelection', $interclub->id);

    expect($queries)->toBeLessThan(30);
});

it('toggles a player within its query budget', function (): void {
    buildScenario();

    $interclub = Interclub::where('week_number', 2)
        ->where('visited_team_id', $this->teams['B']->id)
        ->firstOrFail();

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $interclub->id);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $component->call('togglePlayer', $this->teams['B']->users->first()->id);

    expect($queries)->toBeLessThan(30);
});
