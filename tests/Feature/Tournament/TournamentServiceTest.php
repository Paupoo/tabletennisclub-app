<?php

declare(strict_types=1);

use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentService;

it('counts registered users and returns correct user count', function () {
    $tournament = Tournament::factory()->create();

    $users = User::factory()->count(5)->create();
    $tournament->users()->attach($users->pluck('id'));

    $service = new TournamentService;
    $count = $service->countRegisteredUsers($tournament);

    expect($count)->toBe(5);
    expect($tournament->fresh()->total_users)->toBe(5);

});

it('checks if a tournament is full', function () {
    $tournament = new Tournament;
    $tournament->total_users = 20;
    $tournament->max_users = 25;

    expect((new TournamentService)->isFull($tournament))->toBeFalse();

    $tournament = new Tournament;
    $tournament->total_users = 20;
    $tournament->max_users = 20;

    expect((new TournamentService)->isFull($tournament))->toBeTrue();
    $tournament = new Tournament;
    $tournament->total_users = 25;
    $tournament->max_users = 20;

    expect((new TournamentService)->isFull($tournament))->toBeTrue();
});
