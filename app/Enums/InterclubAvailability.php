<?php

namespace App\Enums;

enum InterclubAvailability: string
{
    CASE UNKNOWN = 'UNKNOWN';
    CASE WANT_TO_PLAY = 'WANT_TO_PLAY';
    CASE CAN_PLAY = 'CAN_PLAY';
    CASE CAN_NOT_PLAY = 'CAN_NOT_PLAY';

    public function getLabel(): string
    {
        return match($this) {
            self::UNKNOWN => __('Unknown'),
            self::WANT_TO_PLAY => __('Wants to play'),
            self::CAN_PLAY => __('Available to play'),
            self::CAN_NOT_PLAY => __('Cannot play'),
        };
    }
}
