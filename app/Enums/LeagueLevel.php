<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueLevel: string
{
    case NATIONAL = 'National';
    case PROVINCIAL_BW = 'Provincial BW';
    case REGIONAL = 'Regional';
}
