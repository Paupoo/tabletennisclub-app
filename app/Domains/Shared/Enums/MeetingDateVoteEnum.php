<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum MeetingDateVoteEnum: string
{
    case AVAILABLE = 'available';
    case MAYBE = 'maybe';
    case UNAVAILABLE = 'unavailable';

    public function getColor(): string
    {
        return match ($this) {
            self::AVAILABLE => 'text-success',
            self::MAYBE => 'text-warning',
            self::UNAVAILABLE => 'text-error',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::AVAILABLE => 'o-check-circle',
            self::MAYBE => 'o-question-mark-circle',
            self::UNAVAILABLE => 'o-x-circle',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => __('Available'),
            self::MAYBE => __('Maybe'),
            self::UNAVAILABLE => __('Unavailable'),
        };
    }
}
