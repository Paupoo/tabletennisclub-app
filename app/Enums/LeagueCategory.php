<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueCategory: string
{
    case MEN = 'Men';
    case WOMEN = 'Women';
    case VETERANS = 'Veterans';
}
