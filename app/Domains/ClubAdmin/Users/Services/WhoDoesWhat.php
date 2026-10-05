<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ClubDuty;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Who runs the club, as a member asking « who do I talk to » needs to read it.
 *
 * Nothing here is typed in by hand: the committee is whoever holds the seat,
 * and each duty lists whoever holds its délégation. Change a délégation and
 * the page follows, so it cannot drift from who actually holds the keys.
 */
class WhoDoesWhat
{
    /**
     * The committee, statutory titles first, in the order the statutes rank them.
     *
     * @return Collection<int, User>
     */
    public function committee(): Collection
    {
        $rank = array_flip(array_map(
            static fn (CommitteeRolesEnum $title): string => $title->value,
            [CommitteeRolesEnum::PRESIDENT, CommitteeRolesEnum::VICE_PRESIDENT, CommitteeRolesEnum::SECRETARY, CommitteeRolesEnum::TREASURER, CommitteeRolesEnum::ADMINISTRATOR],
        ));

        return User::role(Role::COMMITTEE->value)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('users.id')
            ->get()
            ->sortBy(static fn (User $member): int => $rank[$member->committee_role?->value] ?? count($rank))
            ->values();
    }

    /**
     * Each duty somebody holds, with its holders in alphabetical order. A duty
     * nobody holds is left out rather than shown empty.
     *
     * @return Collection<int, array{duty: ClubDuty, holders: EloquentCollection<int, User>}>
     */
    public function duties(): Collection
    {
        return collect(ClubDuty::cases())
            ->map(static fn (ClubDuty $duty): array => [
                'duty' => $duty,
                'holders' => User::role(array_map(static fn (Role $role): string => $role->value, $duty->roles()))
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->orderBy('users.id')
                    ->get(),
            ])
            ->filter(static fn (array $entry): bool => $entry['holders']->isNotEmpty())
            ->values();
    }

    /**
     * Whether the page shows this member at all: a committee seat, or a
     * délégation one of the listed duties answers to.
     */
    public function lists(User $member): bool
    {
        $roles = collect(ClubDuty::cases())
            ->flatMap(static fn (ClubDuty $duty): array => $duty->roles())
            ->push(Role::COMMITTEE)
            ->map(static fn (Role $role): string => $role->value)
            ->unique()
            ->all();

        return $member->hasAnyRole($roles);
    }
}
