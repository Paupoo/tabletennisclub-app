<?php

declare(strict_types=1);

namespace App\Data\Attestation;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Enums\AttestationRefusal;

/**
 * Whether the club may certify this member's affiliation, and why not.
 *
 * Carries the affiliation it looked at: every caller that gets a yes goes on to
 * read the amount and the dates off it, and re-querying would let the answer
 * and the document drift apart.
 */
final readonly class AttestationVerdict
{
    private function __construct(
        public bool $allowed,
        public ?AttestationRefusal $refusal,
        public ?Subscription $affiliation,
        /**
         * What is still owed, when that is the reason for the refusal.
         *
         * Carried rather than recomputed by the screen: « votre cotisation
         * n'est pas entièrement payée » in front of an affiliation the office
         * has already marked *paid* reads as a bug. Naming the five euros is
         * what turns it into something the member can act on.
         */
        public float $balanceDue = 0.0,
    ) {}

    public static function allow(Subscription $affiliation): self
    {
        return new self(true, null, $affiliation);
    }

    public static function refuse(
        AttestationRefusal $refusal,
        ?Subscription $affiliation = null,
        float $balanceDue = 0.0,
    ): self {
        return new self(false, $refusal, $affiliation, $balanceDue);
    }
}
