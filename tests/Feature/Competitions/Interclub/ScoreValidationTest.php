<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubResultEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->admin = User::factory()->isAdmin()->create();
    $this->ourClub = Club::factory()->create(['licence' => config('app.club_licence')]);
});

function makeTeamWithInterclubResult(Season $season, Club $ourClub, string $category): array
{
    $league = League::factory()->create([
        'season_id' => $season->id,
        'category' => $category,
        'division' => '1A',
    ]);

    $team = Team::factory()->create([
        'name' => 'A',
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $ourClub->id,
    ]);

    $matchResult = InterclubResult::factory()->create([
        'team_id' => $team->id,
        'season_id' => $season->id,
        'is_home' => true,
        'result' => null,
        'score' => null,
    ]);

    return compact('team', 'matchResult');
}

// ── Hommes (max 16 points) ─────────────────────────────────────────────────────

it('accepts a valid men score summing to 16', function (): void {
    ['matchResult' => $mr] = makeTeamWithInterclubResult($this->season, $this->ourClub, 'MEN');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $mr->id)
        ->set('matchType', 'normal')
        ->set('scoreUs', 10)
        ->set('scoreThem', 6)
        ->call('save')
        ->assertHasNoErrors(['scoreUs', 'scoreThem']);

    expect(InterclubResult::find($mr->id)->score)->toBe('10-6');
});

it('rejects a men score whose sum is not 16', function (): void {
    ['matchResult' => $mr] = makeTeamWithInterclubResult($this->season, $this->ourClub, 'MEN');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $mr->id)
        ->set('matchType', 'normal')
        ->set('scoreUs', 15)
        ->set('scoreThem', 2)
        ->call('save')
        ->assertHasErrors(['scoreThem']);
});

it('rejects a men score exceeding 16 per side', function (): void {
    ['matchResult' => $mr] = makeTeamWithInterclubResult($this->season, $this->ourClub, 'MEN');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $mr->id)
        ->set('matchType', 'normal')
        ->set('scoreUs', 17)
        ->set('scoreThem', 0)
        ->call('save')
        ->assertHasErrors(['scoreUs']);
});

// ── Dames / Vétérans (max 10 points) ──────────────────────────────────────────

it('accepts a valid women score summing to 10', function (): void {
    ['matchResult' => $mr] = makeTeamWithInterclubResult($this->season, $this->ourClub, 'WOMEN');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $mr->id)
        ->set('matchType', 'normal')
        ->set('scoreUs', 6)
        ->set('scoreThem', 4)
        ->call('save')
        ->assertHasNoErrors(['scoreUs', 'scoreThem']);

    expect(InterclubResult::find($mr->id)->score)->toBe('6-4');
});

it('accepts a valid veterans score summing to 10', function (): void {
    ['matchResult' => $mr] = makeTeamWithInterclubResult($this->season, $this->ourClub, 'VETERANS');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $mr->id)
        ->set('matchType', 'normal')
        ->set('scoreUs', 3)
        ->set('scoreThem', 7)
        ->call('save')
        ->assertHasNoErrors(['scoreUs', 'scoreThem']);
});

it('rejects a women score whose sum is not 10', function (): void {
    ['matchResult' => $mr] = makeTeamWithInterclubResult($this->season, $this->ourClub, 'WOMEN');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $mr->id)
        ->set('matchType', 'normal')
        ->set('scoreUs', 8)
        ->set('scoreThem', 4)
        ->call('save')
        ->assertHasErrors(['scoreThem']);
});

it('rejects a women score exceeding 10 per side', function (): void {
    ['matchResult' => $mr] = makeTeamWithInterclubResult($this->season, $this->ourClub, 'WOMEN');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $mr->id)
        ->set('matchType', 'normal')
        ->set('scoreUs', 11)
        ->set('scoreThem', 0)
        ->call('save')
        ->assertHasErrors(['scoreUs']);
});

// ── Score non requis pour les cas spéciaux ─────────────────────────────────────

it('does not require a score for forfeit situations', function (): void {
    ['matchResult' => $mr] = makeTeamWithInterclubResult($this->season, $this->ourClub, 'MEN');

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.results')
        ->call('openEditModal', $mr->id)
        ->set('matchType', 'forfeit_opponent')
        ->call('save')
        ->assertHasNoErrors(['scoreUs', 'scoreThem']);

    expect(InterclubResult::find($mr->id)->result)->toBe(InterclubResultEnum::FORFEIT_WIN);
});
