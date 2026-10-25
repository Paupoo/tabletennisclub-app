<?php

declare(strict_types=1);

namespace App\Data\Attestation;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\Shared\Enums\AttestationRefusal;
use Illuminate\Support\Carbon;

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
        /** The camp line looked at, for a camp attestation. */
        public ?SubscriptionTrainingPack $line = null,
        /** The first day a camp not over yet can be certified. */
        public ?Carbon $availableOn = null,
    ) {}

    public static function allow(Subscription $affiliation, ?SubscriptionTrainingPack $line = null): self
    {
        return new self(true, null, $affiliation, line: $line);
    }

    public static function refuse(
        AttestationRefusal $refusal,
        ?Subscription $affiliation = null,
        float $balanceDue = 0.0,
        ?SubscriptionTrainingPack $line = null,
        ?Carbon $availableOn = null,
    ): self {
        return new self(false, $refusal, $affiliation, $balanceDue, $line, $availableOn);
    }
}
