<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum PlayerExperienceEnum: string
{
    case FEW_MONTHS = 'FEW_MONTHS';
    case FEW_YEARS = 'FEW_YEARS';
    case NONE = 'NONE';
    case RANKED = 'RANKED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::NONE => __('Never played'),
            self::FEW_MONTHS => __('A few months'),
            self::FEW_YEARS => __('A few years'),
            self::RANKED => __('Ranked player'),
        };
    }
}
