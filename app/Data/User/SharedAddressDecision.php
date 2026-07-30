<?php

declare(strict_types=1);

namespace App\Data\User;

/**
 * What becomes of one affiliate who shares their email address with another.
 *
 * Exactly one affiliate on a given address keeps it, because an address
 * identifies a login. What happens to the others depends on who they are: a
 * child is reached through a guardian, an adult is simply left without a login
 * until the club asks them for an address of their own.
 */
readonly class SharedAddressDecision
{
    public function __construct(
        /** Whether this affiliate keeps the address as their own login. */
        public bool $keepsEmail,
        /**
         * The line of the affiliated adult who becomes this child's guardian,
         * when one shares the address. Null when there is none, or when this
         * affiliate is an adult — an adult is never another adult's guardian.
         */
        public ?int $guardianLineNumber = null,
        /**
         * True when the children on this address have no affiliated adult with
         * them: the address belongs to a parent the club records as a guardian
         * without a member account.
         */
        public bool $externalGuardian = false,
        public ?string $guardianEmail = null,
        public ?string $guardianPhone = null,
    ) {}
}
