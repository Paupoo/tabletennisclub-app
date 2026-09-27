<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;

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

    /**
     * The rule behind the band, shown under its box so that nobody has to
     * guess where a 17-year-old or a 39-year-old lands.
     */
    public function hint(?Season $season = null): string
    {
        $season ??= Season::current();

        return match ($this) {
            self::Youth => __('Under 18 today'),
            self::Adult => __('18 or older, not a veteran'),
            self::Veteran => __(':age by the end of the season (:date)', [
                'age' => User::VETERAN_AGE,
                'date' => $season?->end_at->format('d/m/Y') ?? '—',
            ]),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Youth => __('Youth'),
            self::Adult => __('Adults'),
            self::Veteran => __('Veterans'),
        };
    }
}
