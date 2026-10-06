<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Who the yearly survey asks: the active members of the season. A minor or a
 * managed account is reached through its guardians, and every address gets a
 * single mail naming everyone it answers for — a parent of three children is
 * not written to four times.
 */
final class CampaignAudience
{
    /**
     * Each address, with the members it answers for.
     *
     * @param  iterable<int, User>  $members
     * @return SupportCollection<string, SupportCollection<int, User>>
     */
    public function byAddress(iterable $members): SupportCollection
    {
        $groups = [];

        foreach ($members as $member) {
            foreach ($member->contactEmails() as $address) {
                $groups[$address][] = $member;
            }
        }

        return collect($groups)->map(fn (array $group): SupportCollection => collect($group));
    }

    /**
     * The members asked, guardians loaded for their addresses.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        return User::active()
            ->with('guardians')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('users.id')
            ->get();
    }

    /**
     * @return array{members: int, addresses: int, unreachable: int}
     */
    public function summary(): array
    {
        $members = $this->members();

        return [
            'members' => $members->count(),
            'addresses' => $this->byAddress($members)->count(),
            'unreachable' => $members->filter(fn (User $member): bool => $member->contactEmails() === [])->count(),
        ];
    }
}
