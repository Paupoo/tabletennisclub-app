<?php

namespace App\Enums;

enum Recurrence: string
{
    case NONE = 'None';
    case DAILY = 'Daily';
    case WEEKLY = 'Weekly';
    case BIWEEKLY = 'Biweekly';
}
