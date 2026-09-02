<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum Gender: string
{
    case MEN = 'MEN';
    case WOMEN = 'WOMEN';

    public static function options(): array
    {
        return array_map(fn (self $c): array => [
            'id' => $c->value,
            'name' => $c->getLabel(),
        ], self::cases());
    }

    /**
     * Return the localized string of a value
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MEN => __('Men'),
            self::WOMEN => __('Women'),
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
