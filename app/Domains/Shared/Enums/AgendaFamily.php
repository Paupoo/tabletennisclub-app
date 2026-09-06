<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * The three colours the public grid is allowed to use.
 *
 * The grid first carried one colour per activity type — directed, supervised,
 * free play, interclubs, tournament, meeting. Six colours mean a six-line
 * legend, and a square that cannot be read without it. Three families can be
 * held in the head at a glance, and the finer distinction survives where it
 * belongs: written in the square, next to the time.
 */
enum AgendaFamily: string
{
    /** General assemblies: the club meets rather than plays. */
    case CLUB_LIFE = 'club_life';

    /** Home fixtures and tournaments — the club plays for points. */
    case COMPETITION = 'competition';
    /** Every training session, whether coached, supervised or free. */
    case TRAINING = 'training';

    public function label(): string
    {
        return match ($this) {
            self::TRAINING => __('Training'),
            self::COMPETITION => __('Competition'),
            self::CLUB_LIFE => __('Club life'),
        };
    }
}
