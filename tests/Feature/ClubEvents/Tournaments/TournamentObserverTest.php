<?php

declare(strict_types=1);

namespace Tests\Feature\ClubEvents\Tournaments;

use App\Enums\TournamentStatusEnum;
use App\Events\Tournament\NewTournamentPublished;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('Test Tournament Observer', function () {
    it('dispatches event when tournament is published', function () {
        Event::fake();
    
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::DRAFT,
        ]);
    
        $tournament->update([
            'status' => TournamentStatusEnum::PUBLISHED,
        ]);
    
        Event::assertDispatched(NewTournamentPublished::class, function ($event) use ($tournament) {
            return $event->tournament->id === $tournament->id;
        });
    });
})->group('Tournaments', 'Events', 'Observers');