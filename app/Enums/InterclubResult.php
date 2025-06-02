<?php

declare(strict_types=1);

namespace App\Enums;

enum InterclubResult: string
{
    case DRAW = 'Draw';
    case LOSS = 'Loss';
    case WIN = 'Win';
    case WITHDRAWAL = 'Withdrawal';
}
