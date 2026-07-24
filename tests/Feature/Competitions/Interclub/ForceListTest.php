<?php

declare(strict_types=1);

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('set force list are correctly calculated', function (): void {
    // Algorithm: block index per ranking. Every competitor of a ranking shares
    // the same index = cumulative headcount of that ranking or stronger. E6 and
    // NC are merged into the weakest block; NA is out of scope. Non-competitors
    // are skipped and keep force_list = null.
    // Create without events to prevent the observer from recalculating mid-setup.
    $season = Season::factory()->create(['is_active' => true]);

    $d4 = User::withoutEvents(fn () => User::factory()->create([
        'ranking' => 'D4', 'last_name' => 'AAA', 'first_name' => 'AAA',
    ]));
    Subscription::withoutEvents(fn () => Subscription::factory()->for($d4)->create([
        'season_id' => $season->id, 'is_competitive' => true,
    ]));

    $e2a = User::withoutEvents(fn () => User::factory()->create([
        'ranking' => 'E2', 'last_name' => 'AAA', 'first_name' => 'AAA',
    ]));
    Subscription::withoutEvents(fn () => Subscription::factory()->for($e2a)->create([
        'season_id' => $season->id, 'is_competitive' => true,
    ]));

    $e2b = User::withoutEvents(fn () => User::factory()->create([
        'ranking' => 'E2', 'last_name' => 'BBB', 'first_name' => 'AAA',
    ]));
    Subscription::withoutEvents(fn () => Subscription::factory()->for($e2b)->create([
        'season_id' => $season->id, 'is_competitive' => true,
    ]));

    $nc = User::withoutEvents(fn () => User::factory()->create([
        'ranking' => 'NC', 'last_name' => 'AAA', 'first_name' => 'AAA',
    ]));
    Subscription::withoutEvents(fn () => Subscription::factory()->for($nc)->create([
        'season_id' => $season->id, 'is_competitive' => true,
    ]));

    $nonCompetitor = User::withoutEvents(fn () => User::factory()->create());

    RecalculateForceListAction::handle();

    // D4 block (1 player) → cumulative 1.
    // E2 block (2 players) → cumulative 1 + 2 = 3, shared by both.
    // E6-NC block (here only 1 NC) → cumulative 3 + 1 = 4.
    expect($d4->fresh()->force_list)->toBe(1);
    expect($e2a->fresh()->force_list)->toBe(3);
    expect($e2b->fresh()->force_list)->toBe(3);
    expect($nc->fresh()->force_list)->toBe(4);
    expect($nonCompetitor->fresh()->force_list)->toBeNull();
});
