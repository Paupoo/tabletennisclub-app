<?php

declare(strict_types=1);

namespace Tests\Feature\ClubEvents\Tournaments;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Events\Tournament\NewTournamentPublished;
use App\Observers\TournamentObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

// TournamentObserver implements ShouldHandleEventsAfterCommit which prevents it
// from firing inside a test transaction. We call the observer method directly.

describe('Test Tournament Observer', function () {
    it('dispatches event when tournament is published', function () {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        Event::fake();

        $model = $tournament->fresh();
        $model->status = TournamentStatusEnum::PUBLISHED;

        (new TournamentObserver)->updated($model);

        Event::assertDispatched(NewTournamentPublished::class, fn ($event): bool => $event->tournament->id === $tournament->id);
    });
})->group('Tournaments', 'Events', 'Observers');
