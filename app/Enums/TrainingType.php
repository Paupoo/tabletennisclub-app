<?php

declare(strict_types=1);

namespace App\Enums;

enum TrainingType: string
{
    case DIRECTED = 'Directed';
    case FREE = 'Free';
    case SUPERVISED = 'Supervised';
}
