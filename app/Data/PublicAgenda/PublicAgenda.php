<?php

declare(strict_types=1);

namespace App\Data\PublicAgenda;

/**
 * The five-week grid the homepage draws.
 *
 * It carries training sessions, home fixtures, tournaments and general
 * assemblies — and nothing else. An earlier version also published a written
 * "our usual rhythm" line; the grid says the same thing by its own shape, so
 * the sentence was dropped rather than repeated.
 */
readonly class PublicAgenda
{
    /**
     * @param  list<AgendaDay>  $days  Every day of the window, empty squares included.
     * @param  list<AgendaEntry>  $exceptions  Every cancellation in the window, chronological.
     */
    public function __construct(
        public array $days,
        public array $exceptions = [],
    ) {}

    public function isEmpty(): bool
    {
        return array_all($this->days, fn (AgendaDay $day): bool => $day->entries === []);
    }
}
