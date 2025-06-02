<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'Admin';
    case COMITTEE_MEMBER = 'Comittee member';
    case MEMBER = 'Member';
}
