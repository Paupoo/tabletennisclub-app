<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueCategory: string
{
    case MEN = 'MEN';
    case VETERANS = 'VETERANS';
    case WOMEN = 'WOMEN';

    public function getLabel(): string
    {
        return match ($this) {
            self::MEN => __('Men'),
            self::VETERANS => __('Veterans'),
            self::WOMEN => __('Women'),
        };
    }
}
