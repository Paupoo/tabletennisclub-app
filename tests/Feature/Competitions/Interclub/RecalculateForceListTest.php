<?php

declare(strict_types=1);

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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

it('recalculates when a competitor is deleted', function (): void {
    $strong = User::factory()->create(['is_competitor' => true, 'ranking' => 'B2', 'force_list' => null]);
    $weak = User::factory()->create(['is_competitor' => true, 'ranking' => 'D4', 'force_list' => null]);
    $mid = User::factory()->create(['is_competitor' => true, 'ranking' => 'C0', 'force_list' => null]);

    RecalculateForceListAction::handle();

    $strong->delete();

    expect(User::where('is_competitor', true)->whereNotNull('force_list')->count())
        ->toBe(2);

    expect($mid->fresh()->force_list)->toBeLessThan($weak->fresh()->force_list);
});

it('does not recalculate when a non-competitor is deleted', function (): void {
    $comp = User::factory()->create(['is_competitor' => true, 'ranking' => 'C0', 'force_list' => 1]);
    $nonComp = User::factory()->create(['is_competitor' => false, 'force_list' => null]);

    $nonComp->delete();

    expect($comp->fresh()->force_list)->toBe(1);
});

it('admin can recalculate force list via the users page', function (): void {
    $admin = User::factory()->isAdmin()->create();
    User::factory()->create(['is_competitor' => true, 'ranking' => 'B2', 'force_list' => null]);
    User::factory()->create(['is_competitor' => true, 'ranking' => 'D4', 'force_list' => null]);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.index')
        ->call('recalculateForceList');

    expect(User::where('is_competitor', true)->whereNull('force_list')->count())->toBe(0);
});

it('regular user cannot call recalculateForceList', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.index')
        ->call('recalculateForceList')
        ->assertForbidden();
});
