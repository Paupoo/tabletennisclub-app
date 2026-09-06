<?php

declare(strict_types=1);

namespace App\Data\Interclub;

/**
 * A club as the federation holds it.
 *
 * TabT gives a club no address of its own — only halls. So the postal address we
 * store for a new opponent is its first venue's, which is what the federation
 * itself shows when it has to print one.
 *
 * Two names are published: `Name` is the short form used on a scoresheet
 * ("Muppets"), `longName` the full one ("Muppet's TT Auderghem"). The long form
 * is what a member reading a schedule needs, since three clubs in the province
 * answer to some variant of the same short word.
 */
readonly class AfttClub
{
    public function __construct(
        public string $licence,
        public string $name,
        public string $longName,
        public ?AfttVenue $venue,
    ) {}
}
