<?php

declare(strict_types=1);

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use App\Observers\TournamentObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

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
