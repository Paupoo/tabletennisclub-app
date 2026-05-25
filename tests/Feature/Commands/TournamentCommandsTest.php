<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── tournament:close-registrations ────────────────────────────────────────────

describe('tournament:close-registrations', function (): void {
    it('transitions a PUBLISHED tournament past its deadline to SETUP', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => now()->subDay(),
        ]);

        $this->artisan('tournament:close-registrations')->assertSuccessful();

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::SETUP);
    });

    it('does not transition a PUBLISHED tournament with a future deadline', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => now()->addDay(),
        ]);

        $this->artisan('tournament:close-registrations')->assertSuccessful();

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);
    });

    it('does not affect tournaments that are not PUBLISHED', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::DRAFT,
            'registration_deadline' => now()->subDay(),
        ]);

        $this->artisan('tournament:close-registrations')->assertSuccessful();

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::DRAFT);
    });

    it('returns EXIT_SUCCESS when no tournaments need closing', function (): void {
        $this->artisan('tournament:close-registrations')->assertSuccessful();
    });
});

// ── tournament:process-deadlines ──────────────────────────────────────────────

describe('tournament:process-deadlines', function (): void {
    it('runs successfully with no data', function (): void {
        $this->artisan('tournament:process-deadlines')->assertSuccessful();
    });
});
