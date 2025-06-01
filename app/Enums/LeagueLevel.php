<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueLevel: string
{
    case NATIONAL = 'National';
    case REGIONAL = 'Regional';
    case PROVINCIAL_BW = 'Provincial BW';
}
