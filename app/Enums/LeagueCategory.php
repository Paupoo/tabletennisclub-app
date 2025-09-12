<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueCategory: string
{
    case MEN = 'men';
    case VETERANS = 'veterans';
    case WOMEN = 'women';
}
