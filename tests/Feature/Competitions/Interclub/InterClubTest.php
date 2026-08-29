<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

/*
 * Séparation des devoirs, telle que Role::INTERCLUBS la décrit : « gérer le
 * calendrier et choisir qui joue sont deux délégations que le club distribue
 * indépendamment ». Le centre de contrôle ne la respectait pas — il n'appelait
 * aucune autorisation, et un délégué interclubs pouvait y composer n'importe
 * quelle équipe. La fusion referme cela.
 */
test('the selections screen is closed to the interclubs délégation', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.interclubs.captain-selection'))
        ->assertOK();

    $committee_member = $this->createFakeCommitteeMember();
    $committee_member->assignRole(Role::INTERCLUBS->value);

    $this->actingAs($committee_member)
        ->get(route('admin.interclubs.captain-selection'))
        ->assertForbidden();
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
/*
 * Le centre de contrôle a fusionné avec l'écran des sélections : il en était la
 * transposée (une journée, toutes les équipes) et dupliquait tout le reste. Son
 * ancienne URL redirige, pour ne pas casser un signet.
 */
test('the old control-center url redirects to the selections screen', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->get('/admin/club-events/interclubs/control-center')
        ->assertRedirect(route('admin.interclubs.captain-selection'));
});

test('the selections screen is not accessible to plain members', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('admin.interclubs.captain-selection'))
        ->assertForbidden();
});

describe('selections filter drawer', function (): void {
    test('season filter chip appears only when a non-active season is selected', function (): void {
        $admin = $this->createFakeAdmin();
        $activeSeason = Season::where('is_active', true)->first();
        $otherSeason = Season::factory()->create(['is_active' => false]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.interclubs.captain-selection')
            ->assertSet('selectedSeasonId', $activeSeason->id)
            ->assertViewHas('filterChips', [])
            ->set('selectedSeasonId', $otherSeason->id)
            ->assertViewHas('filterChips', fn ($chips): bool => count($chips) === 1 && $chips[0]['key'] === 'selectedSeasonId');
    });

    test('removing the season chip resets to the active season', function (): void {
        $admin = $this->createFakeAdmin();
        $activeSeason = Season::where('is_active', true)->first();
        $otherSeason = Season::factory()->create(['is_active' => false]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.interclubs.captain-selection')
            ->set('selectedSeasonId', $otherSeason->id)
            ->call('removeFilter', 'selectedSeasonId')
            ->assertSet('selectedSeasonId', $activeSeason->id);
    });

    test('show issues only toggle appears as a removable chip', function (): void {
        $admin = $this->createFakeAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.interclubs.captain-selection')
            ->set('filterAlerts', true)
            ->assertViewHas('filterChips', fn ($chips): bool => count($chips) === 1 && $chips[0]['key'] === 'filterAlerts')
            ->call('clearFilters')
            ->assertSet('filterAlerts', false);
    });
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
