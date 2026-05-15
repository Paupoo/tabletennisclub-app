<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Services\TournamentService;

it('counts registered users and returns correct user count', function (): void {
    $tournament = Tournament::factory()->create();

    $users = User::factory()->count(5)->create();
    $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

    $service = new TournamentService;
    $count = $service->countRegisteredUsers($tournament);

    expect($count)->toBe(5);
    expect($tournament->fresh()->total_users)->toBe(5);
});

it('counts only active registrations, not waitlisted or cancelled', function (): void {
    $tournament = Tournament::factory()->create(['max_users' => 2]);

    $active = User::factory()->count(2)->create();
    $waiting = User::factory()->create();
    $cancelled = User::factory()->create();

    $tournament->users()->attach($active->pluck('id'), ['registration_status' => 'registered']);
    $tournament->users()->attach($waiting->id, ['registration_status' => 'waiting', 'waitlist_position' => 1]);
    $tournament->users()->attach($cancelled->id, ['registration_status' => 'cancelled']);

    $service = new TournamentService;
    $count = $service->countRegisteredUsers($tournament);

    expect($count)->toBe(2);
    expect($service->isFull($tournament))->toBeTrue();
});

it('checks if a tournament is full using real DB count', function (): void {
    $service = new TournamentService;

    $notFull = Tournament::factory()->create(['max_users' => 3]);
    $users = User::factory()->count(2)->create();
    $notFull->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);
    expect($service->isFull($notFull))->toBeFalse();

    $full = Tournament::factory()->create(['max_users' => 2]);
    $full->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);
    expect($service->isFull($full))->toBeTrue();

    $unlimited = Tournament::factory()->create(['max_users' => 0]);
    $unlimited->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);
    expect($service->isFull($unlimited))->toBeFalse();
});
