<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentStatusManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── getAllowedNextStatuses ─────────────────────────────────────────────────────

it('returns PUBLISHED as the only allowed next status for DRAFT', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
    $manager = new TournamentStatusManager($tournament);

    expect($manager->getAllowedNextStatuses())->toBe([TournamentStatusEnum::PUBLISHED]);
});

it('returns DRAFT, LOCKED and CANCELLED as allowed next statuses for PUBLISHED', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
    $manager = new TournamentStatusManager($tournament);

    expect($manager->getAllowedNextStatuses())->toContain(TournamentStatusEnum::DRAFT)
        ->and($manager->getAllowedNextStatuses())->toContain(TournamentStatusEnum::LOCKED)
        ->and($manager->getAllowedNextStatuses())->toContain(TournamentStatusEnum::CANCELLED);
});

it('returns an empty array for CLOSED (terminal status)', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::CLOSED]);
    $manager = new TournamentStatusManager($tournament);

    expect($manager->getAllowedNextStatuses())->toBe([]);
});

it('returns an empty array for CANCELLED (terminal status)', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::CANCELLED]);
    $manager = new TournamentStatusManager($tournament);

    expect($manager->getAllowedNextStatuses())->toBe([]);
});

// ── setStatus ─────────────────────────────────────────────────────────────────

it('transitions from DRAFT to PUBLISHED and persists to database', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
    $manager = new TournamentStatusManager($tournament);

    $manager->setStatus(TournamentStatusEnum::PUBLISHED);

    expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);
});

it('transitions from PUBLISHED to CANCELLED', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
    $manager = new TournamentStatusManager($tournament);

    $manager->setStatus(TournamentStatusEnum::CANCELLED);

    expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::CANCELLED);
});

it('throws InvalidArgumentException when transition is not allowed', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::CANCELLED]);
    $manager = new TournamentStatusManager($tournament);

    expect(fn (): mixed => $manager->setStatus(TournamentStatusEnum::PUBLISHED))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when trying to go from DRAFT to CANCELLED (not in allowed list)', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
    $manager = new TournamentStatusManager($tournament);

    expect(fn (): mixed => $manager->setStatus(TournamentStatusEnum::CANCELLED))
        ->toThrow(InvalidArgumentException::class);
});
