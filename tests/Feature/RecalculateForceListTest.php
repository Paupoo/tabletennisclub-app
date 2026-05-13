<?php

declare(strict_types=1);

use App\Actions\User\RecalculateForceListAction;
use App\Models\ClubAdmin\Users\User;

it('assigns force_list ordered by ranking', function (): void {
    $b2 = User::factory()->create(['is_competitor' => true, 'ranking' => 'B2', 'force_list' => null]);
    $c4 = User::factory()->create(['is_competitor' => true, 'ranking' => 'C4', 'force_list' => null]);
    $e6 = User::factory()->create(['is_competitor' => true, 'ranking' => 'E6', 'force_list' => null]);
    $nc = User::factory()->create(['is_competitor' => true, 'ranking' => 'NC', 'force_list' => null]);

    RecalculateForceListAction::handle();

    expect($b2->fresh()->force_list)->toBeLessThan($c4->fresh()->force_list);
    expect($c4->fresh()->force_list)->toBeLessThan($e6->fresh()->force_list);
    expect($e6->fresh()->force_list)->toBeLessThan($nc->fresh()->force_list);
});

it('excludes non-competitors from force_list calculation', function (): void {
    User::factory()->create(['is_competitor' => true,  'ranking' => 'C4', 'force_list' => null]);
    $nonComp = User::factory()->create(['is_competitor' => false, 'ranking' => 'B2', 'force_list' => null]);

    RecalculateForceListAction::handle();

    expect($nonComp->fresh()->force_list)->toBeNull();
});

it('recalculates when a user becomes a competitor', function (): void {
    $strong = User::factory()->create(['is_competitor' => true,  'ranking' => 'B2', 'force_list' => null]);
    $new = User::factory()->create(['is_competitor' => false, 'ranking' => 'C4', 'force_list' => null]);

    $new->update(['is_competitor' => true]);

    expect($strong->fresh()->force_list)->not->toBeNull();
    expect($new->fresh()->force_list)->not->toBeNull();
    expect($strong->fresh()->force_list)->toBeLessThan($new->fresh()->force_list);
});

it('recalculates when a competitor ranking changes', function (): void {
    $a = User::factory()->create(['is_competitor' => true, 'ranking' => 'D2', 'force_list' => null]);
    $b = User::factory()->create(['is_competitor' => true, 'ranking' => 'C4', 'force_list' => null]);

    $a->update(['ranking' => 'B0']);

    expect($a->fresh()->force_list)->toBeLessThan($b->fresh()->force_list);
});

it('does not recalculate when an unrelated field changes', function (): void {
    $user = User::factory()->create(['is_competitor' => true, 'ranking' => 'B2', 'force_list' => 1]);
    $initialForceList = $user->force_list;

    $user->update(['first_name' => 'Nouveau']);

    expect($user->fresh()->force_list)->toBe($initialForceList);
});
