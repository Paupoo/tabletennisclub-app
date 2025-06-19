<?php

declare(strict_types=1);
use App\Models\User;

uses(\Tests\Trait\CreateUser::class);

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
});
test('set force list are correctly calculated', function (): void {
    /**
     * 1 D4 => 1
     * 1 D6 => 2    (+1)
     * 1 E0 => 3    (+1)
     * 3 E2 => 6    (+3)
     * 1 E4 => 7    (+1)
     * 2 E6 => 11   (+4)
     * 2 NC => 11   (included with E6)
     */
    $checkReferences = [
        'D4' => 1,
        'D6' => 2,
        'E0' => 3,
        'E2' => 6,
        'E4' => 7,
        'E6' => 11,
        'NC' => 11,
    ];

    foreach ($checkReferences as $ranking => $force_list) {
        foreach (User::select('force_list')->where('is_competitor', true)->where('ranking', $ranking)->get() as $user) {
            expect($user->force_list)->toEqual($force_list);
        }
    }
});
