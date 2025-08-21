<?php

namespace App\Enums;

enum ContactReasonEnum: string
{
    CASE join = 'join';
    CASE try = 'try';
    CASE info_interclubs = 'info_interclubs';
    CASE become_supporter = 'become_supporter';
    CASE partnership = 'partnership';

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
