<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueLevel: string
{
    case NATIONAL = 'national';
    case PROVINCIAL_BW = 'provincial_bw';
    case REGIONAL = 'regional';
}
