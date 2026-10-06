<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * How often a member offering help would give a hand.
 */
enum HelpRhythm: string
{
    case Occasional = 'occasional';
    case Regular = 'regular';

    public function label(): string
    {
        return match ($this) {
            self::Occasional => __('Now and then'),
            self::Regular => __('Regularly'),
        };
    }
}
