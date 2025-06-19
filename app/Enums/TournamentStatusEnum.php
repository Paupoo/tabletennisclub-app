<?php

declare(strict_types=1);

namespace App\Enums;

enum TournamentStatusEnum: string
{
    case CANCELLED = 'cancelled';
    case CLOSED = 'closed';
    case DRAFT = 'draft';
    case LOCKED = 'locked';
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case SETUP = 'setup';

    /**
     * Return the values of the enum into an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
