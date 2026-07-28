<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MemberMatchOutcome;

/**
 * One affiliate, confronted with what the club already knows.
 *
 * The discrepancies are never applied: they are what the federation says and the
 * club does not, on fields the club owns. They are read on the report and
 * settled by hand, because each of them can just as well mean the match itself
 * is wrong.
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
