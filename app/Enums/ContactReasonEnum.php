<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactReasonEnum: string
{
    case BECOME_SUPPORTER = 'BECOME_SUPPORTER';
    case INFO_INTERCLUBS = 'INFO_INTERCLUBS';
    case JOIN_US = 'JOIN_US';
    case PARTNERSHIP = 'PARTNERSHIP';
    case TRIAL = 'TRIAL';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::JOIN_US => __('Join us'),
            self::TRIAL => __('Have a try'),
            self::INFO_INTERCLUBS => __('Info about competitions'),
            self::BECOME_SUPPORTER => __('Become a supporter'),
            self::PARTNERSHIP => __('Partnership/Sponsoring'),
        };
    }
}
