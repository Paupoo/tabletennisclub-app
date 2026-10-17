<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── tournament:close-registrations ────────────────────────────────────────────

describe('tournament:close-registrations', function (): void {
    it('transitions a PUBLISHED tournament past its deadline to SETUP', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => now()->subDay(),
        ]);
        // Somebody has to have entered: closing registrations on an empty
        // tournament is refused, and has its own test below.
        $tournament->users()->attach(User::factory()->create());

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

// ── The guard the state machine brought with it ───────────────────────────────

describe('tournament:close-registrations — a tournament nobody joined', function (): void {
    /*
     * Closing registrations means "stop taking entries, start building pools".
     * With nobody entered there is nothing to build, so the state machine
     * refuses and the committee has to cancel instead. The command has to say
     * so and carry on: one such tournament must not abort the whole run.
     */
    it('leaves it open and reports it instead of closing it', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => now()->subDay(),
        ]);

        $this->artisan('tournament:close-registrations')
            ->expectsOutputToContain("Left open: {$tournament->name}")
            ->expectsOutputToContain('Done — 0 tournament(s) closed.')
            ->assertSuccessful();

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);
    });

    it('still closes the others in the same run', function (): void {
        $empty = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => now()->subDay(),
        ]);

        $joined = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => now()->subDay(),
        ]);
        $joined->users()->attach(User::factory()->create());

        $this->artisan('tournament:close-registrations')
            ->expectsOutputToContain('Done — 1 tournament(s) closed.')
            ->assertSuccessful();

        expect($empty->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED)
            ->and($joined->fresh()->status)->toBe(TournamentStatusEnum::SETUP);
    });
});

// ── The deadline day is included ──────────────────────────────────────────────

describe('tournament:close-registrations — the deadline day', function (): void {
    /*
     * "Registrations until 20/11" means the 20th is still open. The deadline is
     * stored as a date, at midnight, and the command runs at 00:05: comparing
     * it to now() closed the entries on the morning of the day announced.
     */
    it('keeps registrations open on the deadline day itself', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => '2026-11-20',
        ]);
        $tournament->users()->attach(User::factory()->create());

        $this->travelTo('2026-11-20 00:05');
        $this->artisan('tournament:close-registrations')->assertSuccessful();

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);
    });

    it('closes them the night after the deadline day', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => '2026-11-20',
        ]);
        $tournament->users()->attach(User::factory()->create());

        $this->travelTo('2026-11-21 00:05');
        $this->artisan('tournament:close-registrations')->assertSuccessful();

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::SETUP);
    });
});
