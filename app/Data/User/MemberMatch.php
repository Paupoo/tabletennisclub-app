<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MemberMatchOutcome;

/**
 * One affiliate, confronted with what the club already knows.
 *
 * The discrepancies are what the federation says and the club does not. They are
 * shown before anything is written, because any of them can just as well mean the
 * match itself is wrong — and the reviewer, seeing them, is the one who decides
 * the line is an update. An update then takes the federation's licence number and
 * postal address; the birthdate and the email address are left as they are and
 * settled by hand.
 */
readonly class MemberMatch
{
    /**
     * @param  array<int, string>  $discrepancies
     */
    public function __construct(
        public FederationRow $row,
        public MemberMatchOutcome $outcome,
        public ?User $existing = null,
        public array $discrepancies = [],
    ) {}
}
