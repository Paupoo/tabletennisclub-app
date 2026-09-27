<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/** The licence a member holds on the affiliation that places them in the audience. */
enum AudienceLicence: string
{
    case Competitive = 'competitive';
    case Recreational = 'recreational';

    public function label(): string
    {
        return match ($this) {
            self::Competitive => __('Competitors'),
            self::Recreational => __('Recreational players'),
        };
    }
}
