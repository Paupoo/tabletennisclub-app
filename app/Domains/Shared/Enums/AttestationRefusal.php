<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Why the club will not certify an affiliation today.
 *
 * A refusal is shown to the member, not logged and swallowed: somebody who is
 * told "you are not eligible" without being told why will phone the office,
 * and the office will issue the document by hand.
 */
enum AttestationRefusal: string
{
    case AlreadyIssued = 'already_issued';
    case BalanceDue = 'balance_due';
    case NoAffiliation = 'no_affiliation';

    public function message(): string
    {
        return match ($this) {
            self::AlreadyIssued => __('Your attestation for this season has already been issued. You can download it again below.'),
            self::BalanceDue => __('Your cotisation is not fully paid yet. The club can only certify what it has received.'),
            self::NoAffiliation => __('You have no validated affiliation for the current season.'),
        };
    }
}
