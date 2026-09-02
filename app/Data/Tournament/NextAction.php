<?php

declare(strict_types=1);

namespace App\Data\Tournament;

/**
 * What a tournament is waiting for from the committee.
 *
 * The list counted tournaments; it never said which one needed somebody. The
 * answer depends on the status and on two facts around it -- whether the event
 * post is published, whether the registration deadline has passed -- so it is a
 * rule, not a label, and it belongs beside the state machine rather than in a
 * `match` inside a Blade view.
 */
readonly class NextAction
{
    /**
     * @param  string  $label  What the committee has to do, in their words.
     * @param  string  $url  Where doing it starts.
     * @param  bool  $urgent  Whether it blocks the tournament rather than merely awaiting it.
     */
    public function __construct(
        public string $label,
        public string $url,
        public bool $urgent = false,
    ) {}
}
