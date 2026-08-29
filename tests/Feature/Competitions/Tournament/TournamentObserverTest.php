<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use App\Observers\TournamentObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// The TournamentObserver implements ShouldHandleEventsAfterCommit which prevents
// it from firing inside the test transaction. We call the observer method directly
// to test its business logic without relying on the Eloquent lifecycle.

it('dispatches NewTournamentPublished when transitioning from DRAFT to PUBLISHED', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

    Event::fake();

    // Simulate the model as seen by the observer: original = DRAFT, current = PUBLISHED
    $model = $tournament->fresh();
    $model->status = TournamentStatusEnum::PUBLISHED;

    (new TournamentObserver)->updated($model);

    Event::assertDispatched(NewTournamentPublished::class, fn ($event): bool => $event->tournament->id === $tournament->id);
});

it('does not dispatch event when status changes from DRAFT to something other than PUBLISHED', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

    Event::fake();

    $model = $tournament->fresh();
    $model->status = TournamentStatusEnum::CANCELLED;

    (new TournamentObserver)->updated($model);

    Event::assertNotDispatched(NewTournamentPublished::class);
});

it('does not dispatch event when an unrelated field changes', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

    Event::fake();

    // Original and current status are both DRAFT
    $model = $tournament->fresh();
    $model->name = 'Nouveau nom';

    (new TournamentObserver)->updated($model);

    Event::assertNotDispatched(NewTournamentPublished::class);
});

it('does not dispatch event when already-PUBLISHED tournament is updated', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);

    Event::fake();

    $model = $tournament->fresh();
    $model->status = TournamentStatusEnum::CANCELLED;

    (new TournamentObserver)->updated($model);

    Event::assertNotDispatched(NewTournamentPublished::class);
});

// ── Reaching the announcement at all (issue #81) ─────────────────────────────

/*
 * The observer used to watch draft → published only, and the wizard never makes
 * that hop: it walks draft → locked → published. The announcement had therefore
 * never been sent to anybody since the feature was written.
 *
 * The gap is closed by two facts that compose, and both are asserted here: the
 * wizard really produces locked → published, and the observer really fires on
 * that pair. It cannot be one end-to-end test — the observer implements
 * ShouldHandleEventsAfterCommit, and RefreshDatabase's transaction never
 * commits, which is exactly why the original tests hand-built their model and
 * why nobody noticed the trigger was unreachable.
 */

it('fires on the locked to published hop the wizard actually makes', function (): void {
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::LOCKED,
        'duration_minutes' => 180,
        'nb_pools' => 2,
        'pool_size' => 4,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'logistics_buffer_minutes' => 3,
        'match_type' => 'single',
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ]);

    // Fact 1 — the wizard produces exactly this transition.
    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
        ->call('confirmOpenRegistrations');

    expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);

    // Fact 2 — the observer fires on it.
    Event::fake();

    $model = $tournament->fresh();
    $model->status = TournamentStatusEnum::LOCKED;
    $model->syncOriginal();
    $model->status = TournamentStatusEnum::PUBLISHED;

    (new TournamentObserver)->updated($model);

    Event::assertDispatched(NewTournamentPublished::class);
});

/* Reopening is not news: the members were already told when it first opened. */
it('stays quiet when a closed tournament is reopened', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::SETUP]);

    Event::fake();

    $model = $tournament->fresh();
    $model->status = TournamentStatusEnum::PUBLISHED;

    (new TournamentObserver)->updated($model);

    Event::assertNotDispatched(NewTournamentPublished::class);
});
