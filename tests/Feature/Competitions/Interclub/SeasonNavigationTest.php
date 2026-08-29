<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

pest()->group('interclubs', 'designSystem');

/*
 * DS-A: a criterion that *determines what the page is about* — exactly one
 * value, never empty — is navigation. It stays visible above the content and
 * its label titles what follows. A criterion that *narrows a set* is a filter:
 * it lives in the drawer and shows up as a removable chip.
 *
 * The interclub schedule renders nothing without a season, so the season is
 * navigation. It used to hide in the filter drawer while the page told the
 * reader to "select a season" — an instruction pointing at a control nothing
 * on screen announced.
 *
 * captain-selection is deliberately out of scope: the season was arbitrated as
 * a filter there, and that file is closed.
 */

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    actingAs($this->admin);

    $this->season = Season::factory()->create(['is_active' => true]);
});

it('stops offering the season as a removable filter chip', function (): void {
    $other = Season::factory()->create(['is_active' => false]);

    $chips = Livewire::test('pages::club-events.interclubs.interclubs')
        ->set('seasonId', $other->id)
        ->get('filterChips');

    expect(collect($chips)->pluck('key')->all())->not->toContain('seasonId');
});

it('keeps the season when the reader clears the filters', function (): void {
    $other = Season::factory()->create(['is_active' => false]);

    Livewire::test('pages::club-events.interclubs.interclubs')
        ->set('seasonId', $other->id)
        ->call('clearFilters')
        ->assertSet('seasonId', $other->id);
});

it('stops offering the training season as a filter chip', function (): void {
    $other = Season::factory()->create(['is_active' => false]);

    $chips = Livewire::test('pages::club-events.trainings.index')
        ->set('viewSeasonId', $other->id)
        ->get('filterChips');

    expect(collect($chips)->pluck('key')->all())->not->toContain('viewSeasonId');
});
