<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Data;

use App\Domains\Shared\Enums\AudienceAgeBand;
use App\Domains\Shared\Enums\AudienceBase;
use App\Domains\Shared\Enums\AudienceLicence;
use App\Domains\Shared\Enums\Gender;

/**
 * What the author of a message asked for.
 *
 * Filters of different kinds narrow each other down (women *and* competitors);
 * several values of one kind widen it (youth *or* veterans). An empty list
 * means the filter is off.
 */
final readonly class AudienceCriteria
{
    /**
     * @param  list<AudienceLicence>  $licences
     * @param  list<Gender>  $genders
     * @param  list<AudienceAgeBand>  $ageBands
     * @param  list<int>  $excludedUserIds  Members left out of this one message by hand.
     * @param  list<int>  $includedUnclassifiedIds  Members the filters could not place, kept in by hand.
     */
    public function __construct(
        public AudienceBase $base = AudienceBase::Active,
        public array $licences = [],
        public array $genders = [],
        public array $ageBands = [],
        public array $excludedUserIds = [],
        public array $includedUnclassifiedIds = [],
    ) {}
}
