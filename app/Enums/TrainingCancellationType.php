<?php

declare(strict_types=1);

namespace App\Enums;

enum TrainingCancellationType: string
{
    /** Room is completely inaccessible */
    case CLOSED = 'Closed';
    /** Room is open for free practice, no coach */
    case FREE = 'Free';
}
