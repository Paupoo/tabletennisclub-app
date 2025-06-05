<?php

declare(strict_types=1);
use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\TournamentStatusManager;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can cancel from pending', function () {
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
    ]);

    $manager = new TournamentStatusManager($tournament);
    $manager->setStatus(TournamentStatusEnum::CANCELLED);

    expect($tournament->fresh()->status)->toEqual(TournamentStatusEnum::CANCELLED);
});
test('can change status from draft to published', function () {
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::DRAFT,
    ]);

    $manager = new TournamentStatusManager($tournament);
    $manager->setStatus(TournamentStatusEnum::PUBLISHED);

    expect($tournament->fresh()->status)->toEqual(TournamentStatusEnum::PUBLISHED);
});
test('can change status from published to locked', function () {
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
    ]);

    $manager = new TournamentStatusManager($tournament);
    $manager->setStatus(TournamentStatusEnum::LOCKED);

    expect($tournament->fresh()->status)->toEqual(TournamentStatusEnum::LOCKED);
});
test('cannot change status from draft to closed', function () {
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::DRAFT,
    ]);

    $manager = new TournamentStatusManager($tournament);

    $this->expectException(InvalidArgumentException::class);
    $manager->setStatus(TournamentStatusEnum::CLOSED);
});
test('cannot lock pending tournament with started matches', function () {
    $tournament = Tournament::factory()
        // ->hasTournamentMatches(1, ['status' => 'in_progress'])
        ->create([
            'status' => TournamentStatusEnum::PENDING,
        ]);

    $match = TournamentMatch::factory()->create([
        'status' => 'in_progress',
    ]);

    $tournament->matches()->save($match);

    $manager = new TournamentStatusManager($tournament);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('At least one match has already started. Is not allow to lock the tournament anymore.');

    $manager->setStatus(TournamentStatusEnum::LOCKED);
});
test('get allowed next statuses returns expected list', function () {
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::LOCKED,
    ]);

    $manager = new TournamentStatusManager($tournament);
    $expected = [
        TournamentStatusEnum::PUBLISHED,
        TournamentStatusEnum::PENDING,
        TournamentStatusEnum::CANCELLED,
    ];

    expect($manager->getAllowedNextStatuses())->toEqualCanonicalizing($expected);
});
test('throws exception on invalid status transition', function () {
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::DRAFT,
    ]);

    $manager = new TournamentStatusManager($tournament);

    $this->expectException(InvalidArgumentException::class);
    $manager->setStatus(TournamentStatusEnum::CLOSED);
    // Not allowed from DRAFT
});
