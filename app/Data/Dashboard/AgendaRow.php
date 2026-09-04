<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

/**
 * One line of a dashboard agenda block.
 *
 * Deliberately free of any link: the blocks carry a single, permission-guarded
 * exit — their "voir tout" — and a line that led somewhere would need a guard of
 * its own, for a destination that is almost always the very screen that exit
 * already points at.
 */
readonly class AgendaRow
{
    /**
     * @param  string  $label  What the line is about, in the reader's words.
     * @param  string  $sub  When it happens, or where — never a second subject.
     * @param  string|null  $badge  A short qualifier the label cannot carry, e.g. "Domicile".
     */
    public function __construct(
        public string $label,
        public string $sub = '',
        public ?string $badge = null,
    ) {}
}
