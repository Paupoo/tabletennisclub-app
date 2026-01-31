<?php

declare(strict_types=1);

namespace App\Enums;

enum Sex
{
    case MEN;
    case OTHER; // Do not use
    case WOMEN;

    /**
     * Returns the values in an array
     *
     * @return array
     */
    public function values(): array
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
            SELF::MEN => __('Men'),
            SELF::WOMEN => __('Woman'),
            SELF::OTHER => __('Other'),
        };
    }
}
