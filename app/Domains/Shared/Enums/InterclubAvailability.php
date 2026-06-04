<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum InterclubAvailability: string
{
    case AVAILABLE = 'available';
    case MAYBE = 'maybe';
    case UNAVAILABLE = 'unavailable';

    public function color(): string
    {
        return match ($this) {
            self::AVAILABLE => 'badge-success',
            self::MAYBE => 'badge-warning',
            self::UNAVAILABLE => 'badge-error',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => __('Available'),
            self::MAYBE => __('Maybe'),
            self::UNAVAILABLE => __('Unavailable'),
        };
    }
}
