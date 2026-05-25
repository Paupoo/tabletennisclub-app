<?php

declare(strict_types=1);

use App\Actions\User\RecalculateForceListAction;
use App\Models\ClubAdmin\Users\User;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('force list are calculated only for competitors', function (): void {
    $admin = $this->createFakeAdmin();
    $response = $this->actingAs($admin)
        ->get(route('setForceList'));

    foreach (User::where('is_competitor', true)->get() as $competitor) {
        expect($competitor->force_list)->toBeInt();
    }

    foreach (User::where('is_competitor', false)->get() as $competitor) {
        expect($competitor->force_list)->toBeNull();
    }

    $response->assertRedirect(route('users.index'));
});
test('force list can be deleted by admin or committee member', function (): void {
    $admin = $this->createFakeAdmin();

    $committee_member = $this->createFakeCommitteeMember();

    $this->actingAs($admin)
        ->get(route('deleteForceList'))
        ->assertRedirect(route('users.index'));

    $this->actingAs($committee_member)
        ->get(route('deleteForceList'))
        ->assertRedirect(route('users.index'));
});
test('force list can be set or updated by admin or committee member', function (): void {
    $admin = $this->createFakeAdmin();

    $committee_member = $this->createFakeCommitteeMember();

    $this->actingAs($admin)
        ->get(route('setForceList'))
        ->assertRedirect(route('users.index'));

    $this->actingAs($committee_member)
        ->get(route('setForceList'))
        ->assertRedirect(route('users.index'));
});
test('force list cant be deleted by members', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('deleteForceList'))
        ->assertStatus(403);
});
test('force list cant be deleted by unlogged users', function (): void {
    $this->get(route('deleteForceList'))
        ->assertRedirect(route('login'));
});
test('force list cant be set or updated by members', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('setForceList'))
        ->assertStatus(403);
});
test('force list cant be set or updated by unlogged users', function (): void {
    $this->get(route('setForceList'))
        ->assertRedirect(route('login'));
});
test('force list delete method removes all force lists from db', function (): void {
    $admin = $this->createFakeAdmin();

    // Check start status
    $totalForceListBeforeDelete = User::whereNotNull('force_list')->count();
    expect($totalForceListBeforeDelete)->toEqual(11);

    // Act: Call the delete method
    $this
        ->actingAs($admin)
        ->get(route('deleteForceList'));

    // Check end status
    $totalForceListAfterlete = User::whereNotNull('force_list')->count();
    expect($totalForceListAfterlete)->toEqual(0);

    $totalNoForceListAfterlete = User::whereNull('force_list')->count();
    $this->assertDatabaseCount('users', $totalNoForceListAfterlete);
})->skip('Requires 11 seeded users with force_list values — depends on DatabaseSeeder data not present in test environment');
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
