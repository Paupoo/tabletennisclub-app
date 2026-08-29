<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum InterclubAvailability: string
{
    case AVAILABLE = 'available';
    case MAYBE = 'maybe';
    case UNAVAILABLE = 'unavailable';

    /**
     * The soft variant, not the solid one: daisyUI pairs a solid status badge
     * with its `-content` colour, which measured 3.53:1 on the error badge —
     * under the AA threshold at any size. The soft variant keeps the same hue
     * over a tint and reads well above it (DS-B).
     */
    public function color(): string
    {
        return match ($this) {
            self::AVAILABLE => 'badge-success badge-soft',
            self::MAYBE => 'badge-warning badge-soft',
            self::UNAVAILABLE => 'badge-error badge-soft',
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
