<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactReasonEnum: string
{
    case become_supporter = 'become_supporter';
    case info_interclubs = 'info_interclubs';
    case join = 'join';
    case partnership = 'partnership';
    case try = 'try';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::join => __('Join us'),
            self::try => __('Have a try'),
            self::info_interclubs => __('Info about competitions'),
            self::become_supporter => __('Become a supporter'),
            self::partnership => __('Partnership/Sponsoring'),
        };
    }
}
