<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum TrainingLevel: string
{
    case BEGINNERS = 'Beginners';
    case ELITE = 'Elite';
    case INTERMEDIATE = 'Intermediate';
    case KIDS = 'Kids';
    case OPEN = 'Open';
    case YOUNG_POTENTIAL = 'Young potential';
}
