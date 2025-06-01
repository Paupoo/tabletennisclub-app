<?php

declare(strict_types=1);

namespace App\Enums;

enum TrainingLevel: string
{
    case BEGINNERS = 'Beginners';
    case KIDS = 'Kids';
    case INTERMEDIATE = 'Intermediate';
    case YOUNG_POTENTIAL = 'Young potential';
    case ELITE = 'Elite';
    case OPEN = 'Open';
}
