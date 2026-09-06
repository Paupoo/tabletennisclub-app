<?php

declare(strict_types=1);

namespace App\Data\PublicAgenda;

use App\Domains\Shared\Enums\AgendaFamily;
use App\Domains\Shared\Enums\TrainingCancellationType;
use Carbon\CarbonImmutable;

/**
 * One activity on the public agenda, whatever object it came from.
 *
 * A cancelled activity is still an entry. Removing it would leave a hole a
 * visitor reads as a display glitch, where a struck-through line carrying its
 * reason actively stops the trip — which is the whole point of the block.
 */
readonly class AgendaEntry
{
    /**
     * @param  CarbonImmutable  $startsAt  When it begins.
     * @param  CarbonImmutable|null  $endsAt  When it ends, when the source knows.
     * @param  AgendaFamily  $family  Which of the three colours the square wears.
     * @param  string  $title  What it is, in a visitor's words.
     * @param  string|null  $location  Where to go — the room a visitor must find.
     * @param  TrainingCancellationType|null  $cancellation  Null while the activity holds.
     * @param  string|null  $cancellationNote  Why it was called off, shown verbatim.
     * @param  CarbonImmutable|null  $spanEndsOn  Last day of a run of consecutive days, e.g. a camp.
     * @param  list<AgendaEntry>  $spanExceptions  The days inside that run that were called off.
     */
    public function __construct(
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public AgendaFamily $family,
        public string $title,
        public ?string $location = null,
        public ?TrainingCancellationType $cancellation = null,
        public ?string $cancellationNote = null,
        public ?CarbonImmutable $spanEndsOn = null,
        public array $spanExceptions = [],
    ) {}

    public function isCancelled(): bool
    {
        return $this->cancellation !== null;
    }

    /**
     * Whether someone can still turn up and play.
     *
     * The distinction the club already records: a session called off because
     * the coach is away leaves the room open, one called off for a general
     * assembly does not.
     */
    public function roomStaysOpen(): bool
    {
        return $this->cancellation === TrainingCancellationType::FREE;
    }

    /**
     * Whether this entry stands for a run of consecutive days.
     *
     * A week-long camp is ten identical rows in the database. Listed one by one
     * they drown every other activity in the window, so the run is folded into
     * a single entry — which then has to carry its own exceptions, or it would
     * reintroduce the very lie the dated agenda exists to remove.
     */
    public function spansMultipleDays(): bool
    {
        return $this->spanEndsOn !== null;
    }
}
