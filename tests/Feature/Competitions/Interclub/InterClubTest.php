<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Trait\CreateInterclub;
use Tests\Trait\CreateUser;

uses(RefreshDatabase::class);
uses(CreateInterclub::class);
uses(CreateUser::class);

beforeEach(function (): void {
    $season = Season::factory()->create(['is_active' => true]);
    $league = League::create([
        'division' => '1A',
        'level' => 'PROVINCIAL_BW',
        'category' => 'MEN',
        'season_id' => $season->id,
    ]);
    $ourClub = Club::factory()->ownClub()->create();
    Club::factory()->create();
    $room = Room::factory()->create([
        'capacity_for_interclubs' => 2,
        'street' => $ourClub->street,
        'city_code' => $ourClub->city_code,
        'city_name' => $ourClub->city_name,
    ]);
    Team::create([
        'name' => 'A',
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $ourClub->id,
    ]);
});

test('admin or comitte member can create interclub', function (): void {
    $admin = $this->createFakeAdmin();

    $this->actingAs($admin)
        ->get(route('admin.interclubs.control-center'))
        ->assertOK();

    $committee_member = $this->createFakeCommitteeMember();

    $this->actingAs($committee_member)
        ->get(route('admin.interclubs.control-center'))
        ->assertOK();
});
test('captains are able to create an interclub', function (): void {
    // to do
})->todo();
test('captains are able to store an interclub', function (): void {
    // to do
})->todo();
test('invalid request', function (): void {
    // to do
})->todo();
test('route control-center accessible to users', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('admin.interclubs.control-center'))
        ->assertOk();
});

describe('matchDayMap', function (): void {
    test('returns empty array for a season with no interclubs', function (): void {
        $season = Season::factory()->create();

        expect(Interclub::matchDayMap($season->id))->toBe([]);
    });

    test('single week maps to match day 1', function (): void {
        $season = Season::factory()->create();
        Interclub::factory()->create([
            'season_id' => $season->id,
            'week_number' => 15,
            'start_date_time' => '2025-09-12 20:00:00',
        ]);

        expect(Interclub::matchDayMap($season->id))->toBe([15 => 1]);
    });

    test('multiple weeks map sequentially ordered by date', function (): void {
        $season = Season::factory()->create();
        Interclub::factory()->create(['season_id' => $season->id, 'week_number' => 3, 'start_date_time' => '2025-09-05 20:00:00']);
        Interclub::factory()->create(['season_id' => $season->id, 'week_number' => 5, 'start_date_time' => '2025-09-19 20:00:00']);
        Interclub::factory()->create(['season_id' => $season->id, 'week_number' => 7, 'start_date_time' => '2025-10-03 20:00:00']);

        expect(Interclub::matchDayMap($season->id))->toBe([3 => 1, 5 => 2, 7 => 3]);
    });

    test('cross-year season maps week numbers in chronological order', function (): void {
        $season = Season::factory()->create();
        Interclub::factory()->create(['season_id' => $season->id, 'week_number' => 50, 'start_date_time' => '2025-12-12 20:00:00']);
        Interclub::factory()->create(['season_id' => $season->id, 'week_number' => 51, 'start_date_time' => '2025-12-19 20:00:00']);
        Interclub::factory()->create(['season_id' => $season->id, 'week_number' => 1,  'start_date_time' => '2026-01-09 20:00:00']);
        Interclub::factory()->create(['season_id' => $season->id, 'week_number' => 2,  'start_date_time' => '2026-01-16 20:00:00']);

        expect(Interclub::matchDayMap($season->id))->toBe([50 => 1, 51 => 2, 1 => 3, 2 => 4]);
    });
});
