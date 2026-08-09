<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\Shared\Enums\Gender;
use Carbon\CarbonImmutable;

/**
 * One affiliate as the federation describes them, already cleaned up but not yet
 * confronted with what the club knows.
 *
 * The two review flags carry the parser's own doubts to the screen where a human
 * settles them. They are not errors: the row is perfectly importable either way,
 * it just deserves a second pair of eyes.
 */
readonly class FederationRow
{
    public function __construct(
        public int $lineNumber,
        public string $licence,
        public string $lastName,
        public string $firstName,
        public ?CarbonImmutable $birthdate,
        public string $ranking,
        public Gender $gender,
        public ?string $federationLicenceType,
        public ?string $email,
        public ?string $phone,
        public ?string $street,
        public ?string $cityCode,
        public ?string $cityName,
        /**
         * The `Nom` column holds first and last name run together, with no
         * separator and no reliable rule: `DE LA FONTAINE CLAIRE` is a
         * three-word surname, `LEROY MARC ANTOINE` a two-word first name.
         * Anything past two words is a guess and says so.
         */
        public bool $needsNameReview = false,
        /**
         * Some exports drop a cell mid-row, which shifts every following column
         * by one. The address is where it shows, and where it must be checked.
         */
        public bool $needsAddressReview = false,
    ) {}
}
