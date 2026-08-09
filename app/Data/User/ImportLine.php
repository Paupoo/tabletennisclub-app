<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\Shared\Enums\ImportLineAction;

/**
 * One line of the federation listing, as the reviewer left it.
 *
 * The row is not necessarily what the parser produced: the reviewer corrects the
 * names the parser could only guess at. What this holds is what will be written,
 * not what was read.
 */
readonly class ImportLine
{
    public function __construct(
        public FederationRow $row,
        public ImportLineAction $action,
        /** The member this line updates, once the reviewer confirmed one. */
        public ?int $existingUserId = null,
        /**
         * Whether this affiliate keeps the listed address as their own login.
         * False when several affiliates were listed under one address and this
         * is not the one who keeps it.
         */
        public bool $keepsEmail = true,
        /**
         * The line of the affiliated adult who becomes this child's guardian.
         * Resolved against `FederationRow::$lineNumber`, so it works before any
         * of the members involved exists.
         */
        public ?int $guardianLineNumber = null,
        /**
         * The address belongs to a parent who does not play. The club records
         * them as a guardian with no member account of their own — which is why
         * their identity has to be supplied here: the listing never carries it.
         */
        public bool $externalGuardian = false,
        public ?string $guardianFirstName = null,
        public ?string $guardianLastName = null,
        public ?string $guardianEmail = null,
        public ?string $guardianPhone = null,
    ) {}
}
