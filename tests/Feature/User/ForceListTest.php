<?php

declare(strict_types=1);

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Users\Models\User;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('set force list are correctly calculated', function (): void {
    // Algorithm: sequential 1-based index ordered by ranking (alpha) → last_name → first_name.
    // Non-competitors are skipped and keep force_list = null.
    // Create without events to prevent the observer from recalculating mid-setup.
    $d4 = User::withoutEvents(fn () => User::factory()->create([
        'is_competitor' => true, 'ranking' => 'D4',
        'last_name' => 'AAA', 'first_name' => 'AAA',
    ]));
    $e2a = User::withoutEvents(fn () => User::factory()->create([
        'is_competitor' => true, 'ranking' => 'E2',
        'last_name' => 'AAA', 'first_name' => 'AAA',
    ]));
    $e2b = User::withoutEvents(fn () => User::factory()->create([
        'is_competitor' => true, 'ranking' => 'E2',
        'last_name' => 'BBB', 'first_name' => 'AAA',
    ]));
    $nc = User::withoutEvents(fn () => User::factory()->create([
        'is_competitor' => true, 'ranking' => 'NC',
        'last_name' => 'AAA', 'first_name' => 'AAA',
    ]));
    $nonCompetitor = User::withoutEvents(fn () => User::factory()->create(['is_competitor' => false]));

    RecalculateForceListAction::handle();

    // D4 comes first alphabetically, then E2 × 2 (ordered by last_name), then NC
    expect($d4->fresh()->force_list)->toBe(1);
    expect($e2a->fresh()->force_list)->toBe(2);
    expect($e2b->fresh()->force_list)->toBe(3);
    expect($nc->fresh()->force_list)->toBe(4);
    expect($nonCompetitor->fresh()->force_list)->toBeNull();
});
