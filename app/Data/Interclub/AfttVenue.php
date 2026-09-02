<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\Shared\Support\AddressNormalizer;

/**
 * The hall a match is played in, as the federation holds it.
 *
 * Carried per match rather than per club on purpose: a club that plays some of
 * its fixtures in a second hall is exactly the case the federation bothers to
 * encode, and the case our own schedule form cannot express.
 *
 * Everything arrives in capitals — see {@see AddressNormalizer} for why that is
 * the export's doing and not a spelling.
 */
readonly class AfttVenue
{
    public function __construct(
        public string $name,
        public string $street,
        public string $town,
    ) {}
}
