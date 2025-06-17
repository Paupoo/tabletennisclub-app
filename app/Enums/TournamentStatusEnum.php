<?php

declare(strict_types=1);

namespace App\Enums;

enum TournamentStatusEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case LOCKED = 'locked';
    case SETUP = 'setup';
    case PENDING = 'pending';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
    
    /**
     * Return the values of the enum into an array
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

