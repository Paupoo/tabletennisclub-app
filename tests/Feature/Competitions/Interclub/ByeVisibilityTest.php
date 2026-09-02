<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

/**
 * A bye is a round in which a team has no opponent. It is imported so the
 * results screens can show it, where "Bye" already means something, and it is
 * kept off every screen that would otherwise render a dated match against
 * nobody.
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

    $this->played = Interclub::factory()->create([
        'aftt_match_id' => 'PBBWH01/001',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'start_date_time' => now()->addDays(7),
        'is_bye' => false,
    ]);

    $this->bye = Interclub::factory()->create([
        'aftt_match_id' => 'PBBWH02/002',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'visiting_team_id' => null,
        'start_date_time' => now()->addDays(14),
        'is_bye' => true,
    ]);
});

/**
 * Both screens hand the view a nested grouping, so the fixtures are gathered
 * back out of it rather than asserted on the shape of the grouping itself, which
 * is presentation and free to change.
 *
 * @param  iterable<mixed>  $grouped
 * @return Collection<int, int>
 */
function fixtureIds(iterable $grouped): Collection
{
    $ids = collect();

    $walk = function ($node) use (&$walk, $ids): void {
        if (is_array($node) && isset($node['id'])) {
            $ids->push($node['id']);

            return;
        }

        if (is_iterable($node)) {
            foreach ($node as $child) {
                $walk($child);
            }
        }
    };

    $walk($grouped);

    return $ids->values();
}

it('keeps a bye off the schedule screen', function (): void {
    $manager = $this->createFakeAdmin();

    $ids = fixtureIds(
        Livewire::actingAs($manager)
            ->test('pages::club-events.interclubs.interclubs')
            ->viewData('grouped')
    );

    expect($ids)->toContain($this->played->id)
        ->not->toContain($this->bye->id);
});

it('keeps a bye out of a member’s own match list', function (): void {
    $ids = fixtureIds(
        Livewire::actingAs($this->player)
            ->test('pages::club-events.interclubs.my-matches')
            ->viewData('grouped')
    );

    expect($ids)->toContain($this->played->id)
        ->not->toContain($this->bye->id);
});

it('never asks a member whether they are free for a bye', function (): void {
    Livewire::actingAs($this->player)
        ->test('pages::club-events.interclubs.my-matches')
        ->call('bulkMarkAvailability', 'available');

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->played->id,
        'user_id' => $this->player->id,
    ]);

    // Answering on a bye would also make it look like a fixture members care
    // about, which is what stops the importer removing it when it disappears.
    $this->assertDatabaseMissing('interclub_user', [
        'interclub_id' => $this->bye->id,
        'user_id' => $this->player->id,
    ]);
});

it('keeps a bye out of the calendar feed', function (): void {
    $events = app(UserCalendarService::class)
        ->eventsFor($this->player, showAllEvents: true)
        ->where('type', 'interclub');

    // Only the fixture that is actually played. A bye would arrive titled
    // "A vs —" and land in somebody's phone calendar as an evening out.
    expect($events)->toHaveCount(1)
        ->and($events->first()['startDateTime'])
        ->toBe($this->played->start_date_time->format('Y-m-d H:i:s'));
});
