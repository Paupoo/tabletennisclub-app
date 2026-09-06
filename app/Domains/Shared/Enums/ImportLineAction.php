<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What the reviewer decided to do with one line of the federation listing.
 *
 * The matcher proposes; this records what a human settled on. The two differ
 * every time the roster and the listing disagree, which is the whole reason the
 * review screen exists.
 *
 * {@see self::UNCHANGED} is the exception: nobody decided it. It is the screen
 * reporting that the listing has nothing to say about a member the club already
 * holds, and it writes exactly as much as that warrants — nothing. It is kept
 * apart from {@see self::SKIP} because the two read the same in the roster and
 * not at all the same in the history: one is a club that was already up to date,
 * the other is a secretary who set somebody aside.
 */
enum ImportLineAction: string
{
    case CREATE = 'create';

    case SKIP = 'skip';

    case UNCHANGED = 'unchanged';

    case UPDATE = 'update';
}
