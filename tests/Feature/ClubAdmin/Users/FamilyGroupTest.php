<?php

declare(strict_types=1);

use App\Actions\User\SyncFamilyGroupMembersAction;
use App\Domains\ClubAdmin\Users\Models\FamilyGroup;
use App\Domains\ClubAdmin\Users\Models\User;

test('family members see each other but not themselves', function (): void {
    $group = FamilyGroup::factory()->create();
    $parent = User::factory()->create();
    $child = User::factory()->create();
    $group->users()->attach([$parent->id, $child->id]);

    expect($parent->familyMembers()->pluck('id')->all())->toBe([$child->id])
        ->and($child->familyMembers()->pluck('id')->all())->toBe([$parent->id]);
});

test('a user with no family group has no family members', function (): void {
    $user = User::factory()->create();

    expect($user->familyMembers())->toBeEmpty();
});

test('syncing family members creates a group when the user has none', function (): void {
    $user = User::factory()->create();
    $sibling = User::factory()->create();

    SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);

    expect($user->fresh()->familyMembers()->pluck('id')->all())->toBe([$sibling->id]);
});

test('syncing family members reuses the existing group instead of creating a new one', function (): void {
    $user = User::factory()->create();
    $sibling = User::factory()->create();
    $newSibling = User::factory()->create();
    SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);
    $groupId = $user->fresh()->familyGroups()->first()->id;

    SyncFamilyGroupMembersAction::handle($user, [$sibling->id, $newSibling->id]);

    expect($user->fresh()->familyGroups()->first()->id)->toBe($groupId)
        ->and($user->fresh()->familyMembers()->pluck('id')->sort()->values()->all())
        ->toBe([$sibling->id, $newSibling->id]);
});

test('syncing a user into a member\'s existing family joins that group and keeps its other members', function (): void {
    $parent = User::factory()->create();
    $childA = User::factory()->create();
    $childB = User::factory()->create();
    SyncFamilyGroupMembersAction::handle($parent, [$childA->id, $childB->id]);
    $groupId = $parent->fresh()->familyGroups()->first()->id;

    $newcomer = User::factory()->create();
    SyncFamilyGroupMembersAction::handle($newcomer, [$parent->id]);

    expect($newcomer->fresh()->familyGroups()->first()->id)->toBe($groupId)
        ->and(FamilyGroup::count())->toBe(1)
        ->and($childA->fresh()->familyMembers()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$parent->id, $childB->id, $newcomer->id])->sort()->values()->all());
});

test('syncing to no members detaches the user and deletes the group if only one member remains', function (): void {
    $user = User::factory()->create();
    $sibling = User::factory()->create();
    SyncFamilyGroupMembersAction::handle($user, [$sibling->id]);
    $groupId = $user->fresh()->familyGroups()->first()->id;

    SyncFamilyGroupMembersAction::handle($user, []);

    expect($user->fresh()->familyMembers())->toBeEmpty()
        ->and(FamilyGroup::find($groupId))->toBeNull();
});

test('syncing to no members keeps the group alive when other members remain', function (): void {
    $user = User::factory()->create();
    $siblingA = User::factory()->create();
    $siblingB = User::factory()->create();
    SyncFamilyGroupMembersAction::handle($user, [$siblingA->id, $siblingB->id]);
    $groupId = $user->fresh()->familyGroups()->first()->id;

    SyncFamilyGroupMembersAction::handle($user, []);

    expect($user->fresh()->familyMembers())->toBeEmpty()
        ->and(FamilyGroup::find($groupId))->not->toBeNull()
        ->and($siblingA->fresh()->familyMembers()->pluck('id')->all())->toBe([$siblingB->id]);
});
