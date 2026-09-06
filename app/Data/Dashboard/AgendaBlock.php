<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

/**
 * One card of the dashboard's agenda column, about exactly one kind of object.
 *
 * The column used to hold a flat "recent activity" feed mixing members, contact
 * messages and match results, rendered to every authenticated member. Splitting
 * it per object is what makes a per-object visibility rule expressible at all.
 *
 * Two independent axes decide what a reader gets, and both are resolved before
 * the view sees a block — a Blade template never calls `can()`:
 *   - the content, gated by an optional permission (its absence means public);
 *   - the exit, gated by whatever permission its target route already declares,
 *     so {@see self::$seeAllRoute} is null when that screen is out of reach.
 */
readonly class AgendaBlock
{
    /**
     * @param  string  $key  Stable identifier, used as the Blade key.
     * @param  string  $label  The card's heading.
     * @param  list<AgendaRow>  $rows  Never empty: a block with nothing to say is not built.
     * @param  string|null  $seeAllRoute  URL of the management screen, or null when unreachable.
     * @param  AgendaRow|null  $lead  A line looking back where the others look forward.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $rows,
        public ?string $seeAllRoute = null,
        public ?AgendaRow $lead = null,
    ) {}
}
