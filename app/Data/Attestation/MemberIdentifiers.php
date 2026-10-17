<?php

declare(strict_types=1);

namespace App\Data\Attestation;

/**
 * The numbers only the member knows, carried for the length of one request.
 *
 * Never persisted, never a column. A national register number is a regulated
 * identifier, and the club has no business becoming its holder for a document
 * it hands straight back. They are typed, printed, and dropped.
 */
final readonly class MemberIdentifiers
{
    public function __construct(
        public ?string $nationalRegisterNumber = null,
        public ?string $mutualMembershipNumber = null,
        /**
         * Whether the participant is recognised as disabled — Partenamut's camp
         * form asks, and pays more when it is so. Health data: answered for this
         * one document, printed as a cross, and never kept.
         */
        public ?bool $recognisedDisabled = null,
    ) {}
}
