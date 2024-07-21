<?php

namespace App\Enums;

enum InterclubResult: string
{
    case WIN = 'Win';
    case LOSS = 'Loss';
    case DRAW = 'Draw';
    case WITHDRAWAL = 'Withdrawal';
}