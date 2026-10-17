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
    case CampBalanceDue = 'camp_balance_due';
    case CampNoAffiliation = 'camp_no_affiliation';
    case CampNotOver = 'camp_not_over';
    case NoAffiliation = 'no_affiliation';
    case NotCertifiable = 'not_certifiable';

    public function message(): string
    {
        return match ($this) {
            self::AlreadyIssued => __('Your attestation for this season has already been issued. You can download it again below.'),
            self::BalanceDue => __('Your cotisation is not fully paid yet. The club can only certify what it has received.'),
            self::CampBalanceDue => __('This training camp is not fully paid yet. The club can only certify what it has received.'),
            self::CampNoAffiliation => __('You had no validated affiliation for the season of this training camp.'),
            self::CampNotOver => __('This training camp is not over yet.'),
            self::NoAffiliation => __('You have no validated affiliation for the current season.'),
            self::NotCertifiable => __('The club cannot issue an attestation for this training camp online. Contact the secretary.'),
        };
    }
}
