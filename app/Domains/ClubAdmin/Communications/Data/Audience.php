<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Data;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Collection;

/**
 * The outcome of an audience: who is reached, and who is not.
 *
 * Nothing is dropped silently. A member with no address anywhere lands in
 * `unreachable`, a member a filter cannot place — no birthdate, no gender — in
 * `unclassified`, so the author sees them and decides.
 */
final readonly class Audience
{
    /**
     * @param  Collection<int, User>  $members  Targeted and reachable.
     * @param  Collection<int, User>  $unreachable  Targeted, but no address on file for them or a guardian.
     * @param  Collection<int, User>  $unclassified  In the base, but missing what a filter needs.
     * @param  array<string, list<User>>  $recipients  Each address, with the members it speaks for.
     */
    public function __construct(
        public Collection $members,
        public Collection $unreachable,
        public Collection $unclassified,
        public array $recipients,
    ) {}

    /** @return list<string> */
    public function addresses(): array
    {
        return array_keys($this->recipients);
    }
}
