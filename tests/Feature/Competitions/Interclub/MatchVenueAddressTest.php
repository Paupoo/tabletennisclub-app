<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * The schedule screen used to prefill a match venue with the club's street
 * alone, so every match read "Rue de l'invasion 80" with no postal code and no
 * city. The address is frozen into interclubs.address when the match is saved,
 * so what the form offers is what the members end up reading.
 */

beforeEach(function (): void {
    Club::forgetOwnClub();

    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
        'division' => 'P2A',
    ]);

    $this->ourClub = Club::factory()->ownClub()->create([
        'street' => "Rue de l'invasion 80",
        'city_code' => '1340',
        'city_name' => 'Ottignies',
    ]);

    $this->ourTeam = Team::factory()->create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $this->ourClub->id,
    ]);

    $this->admin = User::factory()->isAdmin()->create();
});

afterEach(fn () => Club::forgetOwnClub());

// ── Club::address ──────────────────────────────────────────────────────────────

it('composes the club address from street, postal code and city', function (): void {
    expect($this->ourClub->address)->toBe("Rue de l'invasion 80, 1340 Ottignies");
});

it('leaves no dangling comma when the city is unknown', function (): void {
    $club = Club::factory()->create([
        'street' => 'Allée des Sports 5',
        'city_code' => null,
        'city_name' => null,
    ]);

    expect($club->address)->toBe('Allée des Sports 5');
});

it('leaves no dangling street when only the city is known', function (): void {
    $club = Club::factory()->create([
        'street' => null,
        'city_code' => '1300',
        'city_name' => 'Wavre',
    ]);

    expect($club->address)->toBe('1300 Wavre');
});

it('returns null when the club has no address at all', function (): void {
    $club = Club::factory()->create(['street' => null, 'city_code' => null, 'city_name' => null]);

    expect($club->address)->toBeNull();
});

// ── Schedule form prefill ──────────────────────────────────────────────────────

it('prefills a home match with our full club address', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.interclubs')
        ->set('seasonId', $this->season->id)
        ->call('openCreateModal', $this->ourTeam->id)
        ->assertSet('formAddress', "Rue de l'invasion 80, 1340 Ottignies");
});

it('prefills an away match with the opponent full address', function (): void {
    $opponentClub = Club::factory()->create([
        'name' => 'CTT Tubize',
        'street' => 'Allée des Sports 5',
        'city_code' => '1480',
        'city_name' => 'Tubize',
    ]);

    $opponentTeam = Team::factory()->create([
        'name' => 'C',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $opponentClub->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.interclubs')
        ->set('seasonId', $this->season->id)
        ->call('openCreateModal', $this->ourTeam->id)
        ->set('formIsHome', false)
        ->set('formOpponentTeamId', $opponentTeam->id)
        ->assertSet('formAddress', 'Allée des Sports 5, 1480 Tubize');
});

it('keeps the street alone when the opponent has no city yet', function (): void {
    $opponentClub = Club::factory()->create([
        'street' => 'Allée des Sports 5',
        'city_code' => null,
        'city_name' => null,
    ]);

    $opponentTeam = Team::factory()->create([
        'name' => 'D',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $opponentClub->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.interclubs')
        ->set('seasonId', $this->season->id)
        ->call('openCreateModal', $this->ourTeam->id)
        ->set('formIsHome', false)
        ->set('formOpponentTeamId', $opponentTeam->id)
        ->assertSet('formAddress', 'Allée des Sports 5');
});
