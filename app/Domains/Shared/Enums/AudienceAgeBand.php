<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Age bands a message can be aimed at.
 *
 * Youth is read on today's date, like the guardianship it goes with; veterans
 * on the season's end, like the federation's force lists. An adult is whoever
 * is neither.
 */
enum AudienceAgeBand: string
{
    case Adult = 'adult';
    case Veteran = 'veteran';
    case Youth = 'youth';

    public function label(): string
    {
        return match ($this) {
            self::Youth => __('Youth (under 18)'),
            self::Adult => __('Adults'),
            self::Veteran => __('Veterans'),
        };
    }
}
