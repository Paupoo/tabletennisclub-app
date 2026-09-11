<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Services;

use App\Data\Attestation\AttestationVerdict;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationRefusal;

/**
 * Decides whether the club may certify a member's affiliation.
 *
 * Two conditions, and deliberately only two: the affiliation is validated for
 * the season, and it is paid in full — cotisation and training packs alike,
 * since that is the sum the document states.
 *
 * A fine, a tournament entry, a meal or a bar tab left open does NOT block.
 * They are separate debts with their own reminders, and withholding a document
 * that earns the member money elsewhere in order to collect twelve euros turns
 * an administrative certificate into a debt-collection lever.
 */
final readonly class AttestationEligibility
{
    public function for(User $member, Season $season): AttestationVerdict
    {
        $affiliation = Subscription::query()
            ->where('user_id', $member->id)
            ->forSeason($season)
            ->active()
            ->first();

        if (! $affiliation instanceof Subscription) {
            return AttestationVerdict::refuse(AttestationRefusal::NoAffiliation);
        }

        if (! $affiliation->isFullyPaid()) {
            return AttestationVerdict::refuse(AttestationRefusal::BalanceDue, $affiliation);
        }

        return AttestationVerdict::allow($affiliation);
    }
}
