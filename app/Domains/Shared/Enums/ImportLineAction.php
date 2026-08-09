<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What the reviewer decided to do with one line of the federation listing.
 *
 * The matcher proposes; this records what a human settled on. The two differ
 * every time the roster and the listing disagree, which is the whole reason the
 * review screen exists.
 */
enum ImportLineAction: string
{
    case CREATE = 'create';

    case SKIP = 'skip';

    case UPDATE = 'update';
}
