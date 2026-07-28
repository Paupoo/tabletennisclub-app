<?php

declare(strict_types=1);

namespace App\Data\User;

/**
 * The outcome of reading a federation listing: the affiliates that could be made
 * sense of, and the lines that could not.
 *
 * A failure records a line number and a reason and nothing else. The source file
 * carries names, birthdates, postal addresses and phone numbers of minors, and
 * none of that is allowed to survive into the import history.
 */
readonly class ParsedFederationListing
{
    /**
     * @param  array<int, FederationRow>  $rows
     * @param  array<int, array{line: int, reason: string}>  $failures
     */
    public function __construct(
        public array $rows = [],
        public array $failures = [],
    ) {}
}
