<?php

namespace App\Enums;

enum Roles: string
{
    case MEMBER = 'Member';
    case COMITTEE_MEMBER = 'Comittee member';
    case ADMIN = 'Admin';
}