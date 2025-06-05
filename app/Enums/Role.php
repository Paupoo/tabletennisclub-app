<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'Admin';
    case COMMITTEE_MEMBER = 'Committee member';
    case MEMBER = 'Member';
}
