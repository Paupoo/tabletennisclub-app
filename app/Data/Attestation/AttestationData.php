<?php

declare(strict_types=1);

namespace App\Data\Attestation;

use Illuminate\Support\Carbon;

/**
 * Every fact a mutual-insurer form can ask of the club, gathered once.
 *
 * Assembled from the affiliation rather than read field by field at render
 * time: five templates ask for the same facts in five wordings, and a value
 * resolved twice is a value that can differ between two of them.
 *
 * The member's own identifiers — national register number, mutual membership
 * number — are NOT here. They are typed at the moment of the request and go
 * straight into the document; the club never becomes their holder.
 */
final readonly class AttestationData
{
    public function __construct(
        public string $memberFullName,
        public string $memberFirstName,
        public string $memberLastName,
        public ?Carbon $memberBirthdate,
        public string $memberAddress,
        public ?string $memberEmail,
        public ?string $memberPhone,
        public Carbon $periodFrom,
        public Carbon $periodTo,
        public string $seasonLabel,
        public float $amountPaid,
        public float $cotisation,
        public float $trainingsTotal,
        public float $familyCredit,
        public ?string $paymentMethod,
        public string $discipline,
        public string $clubName,
        public string $clubAddress,
        public ?string $clubPhone,
        public string $clubLicence,
        public string $federation,
        public string $signatoryName,
        public Carbon $issuedOn,
    ) {}

    /** The season written the way ML/MutPlus asks for it: two separate years. */
    public function seasonYears(): array
    {
        return [$this->periodFrom->year, $this->periodTo->year];
    }
}
