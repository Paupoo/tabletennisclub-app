<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case MEMBER = 'Member';
    case COMITTEE_MEMBER = 'Comittee member';
    case ADMIN = 'Admin';
}
