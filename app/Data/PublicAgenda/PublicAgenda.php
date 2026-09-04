<?php

declare(strict_types=1);

namespace App\Data\PublicAgenda;

/**
 * Everything the homepage agenda block needs to render itself.
 */
readonly class PublicAgenda
{
    /**
     * @param  list<AgendaDay>  $days  Occupied days only, in chronological order.
     * @param  list<AgendaEntry>  $exceptions  Every cancellation in the window,
     *                                         chronological, lifted clear of the days.
     * @param  list<RhythmDay>  $rhythm  The usual weekly rhythm, by weekday.
     * @param  InterclubRhythm|null  $interclubRhythm  The habitual competition
     *                                                 evening, when the club advertises one.
     * @param  bool  $isExtended  True when the fortnight was empty and the agenda
     *                            reached past it to show the next activity instead.
     */
    public function __construct(
        public array $days,
        public array $exceptions = [],
        public array $rhythm = [],
        public ?InterclubRhythm $interclubRhythm = null,
        public bool $isExtended = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->days === [];
    }
}
