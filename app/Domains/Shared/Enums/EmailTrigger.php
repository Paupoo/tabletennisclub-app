<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What makes the application send an email on its own.
 *
 * The line between the last two is who acts: a member doing something for
 * themself (registering, writing through a form), or somebody in charge
 * clicking a button whose purpose is to tell others. The committee asks the
 * second question — « if I click, what do they receive? » — far more often.
 */
enum EmailTrigger: string
{
    case Action = 'action';
    case Event = 'event';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Action => __('On action'),
            self::Event => __('Event'),
            self::Scheduled => __('Scheduled'),
        };
    }
}
