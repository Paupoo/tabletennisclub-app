<?php

namespace App\Enums;

enum TournamentStatusEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case LOCKED = 'locked';
    case PENDING = 'pending';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}