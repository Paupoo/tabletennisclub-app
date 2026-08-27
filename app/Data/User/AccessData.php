<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\Shared\Enums\CommitteeRolesEnum;

/**
 * The rights layer of a member's file, travelling as one object.
 *
 * Whoever writes a member passes this as `?AccessData`, and the single null is
 * the whole signal: null means "this caller does not manage rights, leave every
 * one of them alone". Passing an instance means the opposite — everything it
 * carries is authoritative, including what it leaves empty.
 *
 * It exists because the signal cannot live on the fields themselves. A null
 * délégations array could mean "do not touch", but a null `committeeRole`
 * already means "no title", and no third value distinguishes the two. Hoisting
 * the signal one level up costs a small class and removes the ambiguity for
 * good — a sentinel constant in a public signature would not have survived its
 * first review.
 */
readonly class AccessData
{
    /**
     * @param  array<int, string>  $delegations  Délégations to hold, as Role values. Authoritative:
     *                                           what is absent is revoked. Names that are not
     *                                           délégations are dropped by the action.
     */
    public function __construct(
        public bool $isAdmin = false,
        public bool $isCommitteeMember = false,
        public ?CommitteeRolesEnum $committeeRole = null,
        public array $delegations = [],
    ) {}
}
