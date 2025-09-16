<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueLevel: string
{
    case NATIONAL = 'NATIONAL';
    case PROVINCIAL_BW = 'PROVINCIAL_BW';
    case REGIONAL = 'REGIONAL';

    public function getLabel(): string
    {
        return match($this) {
            self::NATIONAL => __('National'),
            self::PROVINCIAL_BW => __('Provincial BW'),
            self::REGIONAL => __('Regional'),
        };
    }
}
