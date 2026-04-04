<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case MEN = 'MEN';
    case WOMEN = 'WOMEN';
    case OTHER = 'OTHER';

    /**
     * Return the localized string of a value
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MEN => __('Men'),
            self::WOMEN => __('Women'),
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

    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'id' => $c->value,
            'name' => $c->getLabel(),
        ], self::cases());
    }
}
