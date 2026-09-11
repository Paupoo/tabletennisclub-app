<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

/**
 * Where a value sits relative to the label that names it.
 */
enum AnchorPlacement: string
{
    /** On the same line, starting just after the label ends. */
    case After = 'after';

    /** At the label's own top-left corner — for covering a dotted rule. */
    case At = 'at';

    /** Left-aligned with the label, on the line under it. */
    case Below = 'below';
}
