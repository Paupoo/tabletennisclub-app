<?php

declare(strict_types=1);

namespace App\Enums;

enum Recurrence: string
{
    case BIWEEKLY = 'Biweekly';
    case DAILY = 'Daily';
    case NONE = 'None';
    case WEEKLY = 'Weekly';
}
