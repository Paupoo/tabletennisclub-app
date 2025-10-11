<?php

declare(strict_types=1);

namespace App\Enums;

enum Sex
{
    case MEN;
    case OTHER; // Do not use
    case WOMEN;

    /**
     * Return the localized string of a value
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MEN => __('Men'),
            self::WOMEN => __('Woman'),
            self::OTHER => __('Other'),
        };
    }

    /**
     * Returns the values in an array
     */
    public function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
