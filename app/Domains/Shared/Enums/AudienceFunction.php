<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * A function held in the club this season, which a message can be aimed at.
 *
 * Read on what people do — leading a pack or a session, captaining one of our
 * own teams — never on a role, which is granted late and taken back never.
 */
enum AudienceFunction: string
{
    case Captains = 'captains';
    case Coaches = 'coaches';

    public function label(): string
    {
        return match ($this) {
            self::Captains => __('Team captains'),
            self::Coaches => __('Coaches'),
        };
    }

    /** Said when nobody holds the function yet, typically right after the season switches. */
    public function vacancyWarning(): string
    {
        return match ($this) {
            self::Captains => __('No captain assigned for this season yet: assign the captains to the teams first.'),
            self::Coaches => __('No coach assigned for this season yet: assign the trainers to the training packs first.'),
        };
    }
}
