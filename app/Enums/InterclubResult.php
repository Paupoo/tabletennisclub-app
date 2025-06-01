<?php

declare(strict_types=1);

namespace App\Enums;

enum InterclubResult: string
{
    case WIN = 'Win';
    case LOSS = 'Loss';
    case DRAW = 'Draw';
    case WITHDRAWAL = 'Withdrawal';
}
