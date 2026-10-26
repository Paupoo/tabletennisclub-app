<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubPreparationService;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * « À faire » se lit à 21 jours (grilling 2026-10-25). Avant, un match d'avril
 * prêt à composer y entrait dès octobre, tandis qu'un match à trois semaines
 * sans assez de disponibles restait « À venir ».
 */
beforeEach(function (): void {
    $this->freezeTime(fn (): null => null);
    $this->travelTo('2026-01-15 12:00:00');

    $season = Season::factory()->create(['is_active' => true, 'start_at' => '2025-09-01', 'end_at' => '2026-06-30']);
    Club::factory()->ownClub()->create();

    $this->team = Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => League::factory()->create(['season_id' => $season->id])->id,
        'club_id' => Club::query()->first()->id,
    ]);
    $this->team->users()->attach(User::factory()->isCompetitor()->count(6)->create()->pluck('id'));
    $this->team->load('users');
});

/**
 * A four-player fixture $days days out, with the first $available members
 * available, of whom the first $selected are selected.
 */
function horizonFixture(int $days, int $available = 0, int $selected = 0, int $declined = 0): Interclub
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

    foreach ($team->users->take($available)->values() as $index => $player) {
        $interclub->users()->attach($player->id, [
            'availability' => InterclubAvailability::AVAILABLE->value,
            'is_selected' => $index < $selected,
        ]);
    }

    foreach ($team->users->skip($available)->take($declined) as $player) {
        $interclub->users()->attach($player->id, [
            'availability' => InterclubAvailability::UNAVAILABLE->value,
        ]);
    }

    return $interclub->fresh('users');
}

function horizonStatus(Interclub $interclub): string
{
    return app(InterclubPreparationService::class)->fixtureStatus($interclub);
}

it('leaves a composable fixture beyond 21 days as upcoming', function (): void {
    expect(horizonStatus(horizonFixture(days: 30, available: 5)))->toBe('future')
        ->and(horizonStatus(horizonFixture(days: 20, available: 5)))->toBe('actionable');
});

it('flags a fixture within 21 days that lacks available players', function (): void {
    expect(horizonStatus(horizonFixture(days: 18, available: 2)))->toBe('missing_players')
        // Plus loin, le manque n'est pas encore un travail.
        ->and(horizonStatus(horizonFixture(days: 30, available: 2)))->toBe('future')
        // Sous J-14, l'urgence l'emporte, comme pour tout ce qui n'est pas parti.
        ->and(horizonStatus(horizonFixture(days: 10, available: 2)))->toBe('urgent');
});

it('counts missing players as something to do', function (): void {
    expect(InterclubPreparationService::TO_DO)->toContain('missing_players');
});

it('leaves a saved but unsent lineup beyond 21 days as upcoming', function (): void {
    expect(horizonStatus(horizonFixture(days: 40, available: 4, selected: 4)))->toBe('future')
        ->and(horizonStatus(horizonFixture(days: 20, available: 4, selected: 4)))->toBe('to_send');
});

it('rates a week short of players above one ready to compose', function (): void {
    $other = Team::factory()->create([
        'season_id' => $this->team->season_id,
        'league_id' => $this->team->league_id,
        'club_id' => $this->team->club_id,
    ]);
    $other->users()->attach(User::factory()->isCompetitor()->count(4)->create()->pluck('id'));

    $composable = horizonFixture(days: 18, available: 5);
    $composable->update(['week_number' => 3]);

    $short = Interclub::factory()->create([
        'season_id' => $other->season_id,
        'league_id' => $other->league_id,
        'visited_team_id' => $other->id,
        'visiting_team_id' => null,
        'week_number' => 3,
        'total_players' => 4,
        'start_date_time' => $composable->start_date_time,
    ]);

    $summary = app(InterclubPreparationService::class)->summary(
        Team::with('league')->get(),
        Interclub::with(['users', 'league'])->orderBy('start_date_time')->get(),
    );

    expect(collect($summary['weeks'])->firstWhere('wk', 3)['status'])->toBe('missing_players')
        ->and($summary['matrix'][$other->id][3])->toBe('missing_players')
        ->and($summary['matrix'][$this->team->id][3])->toBe('actionable');
});
