<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\FamilyGroup;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;

class SyncFamilyGroupMembersAction
{
    /**
     * @param  array<int>  $memberIds  Other users to link into the same family group as $user.
     */
    public static function handle(User $user, array $memberIds): void
    {
        $memberIds = array_values(array_unique(array_filter(
            $memberIds,
            fn (int $id): bool => $id !== $user->id,
        )));

        $currentGroup = $user->familyGroups()->first();

        if ($memberIds === []) {
            if ($currentGroup) {
                $currentGroup->users()->detach($user->id);
                self::deleteIfTooSmall($currentGroup);
            }

            return;
        }

        if ($currentGroup) {
            $currentGroup->users()->sync([$user->id, ...$memberIds]);

            return;
        }

        $existingMemberGroup = FamilyGroup::query()
            ->whereHas('users', fn (Builder $query) => $query->whereIn('users.id', $memberIds))
            ->first();

        if ($existingMemberGroup) {
            // Joining a family that already exists: never evict its other members.
            $existingMemberGroup->users()->syncWithoutDetaching([$user->id, ...$memberIds]);

            return;
        }

        FamilyGroup::create()->users()->sync([$user->id, ...$memberIds]);
    }

    private static function deleteIfTooSmall(FamilyGroup $group): void
    {
        if ($group->users()->count() <= 1) {
            $group->users()->detach();
            $group->delete();
        }
    }
}
