<?php

declare(strict_types=1);

namespace App\Enums;

enum Sex: string
{
    case MEN = 'Men';
    case WOMEN = 'Women';
    case OTHER = 'Other'; // Do not use

    /**
     * Returns the values in an array
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return the localized string of a value
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MEN => __('Men'),
            self::WOMEN => __('Women'),
            self::OTHER => __('Other'),
        };
    }
}
