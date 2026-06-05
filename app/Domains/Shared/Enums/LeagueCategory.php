<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum LeagueCategory: string
{
    case MEN = 'Men';
    case VETERANS = 'Veterans';
    case WOMEN = 'Women';
}
