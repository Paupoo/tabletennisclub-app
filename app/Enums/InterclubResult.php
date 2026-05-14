<?php

declare(strict_types=1);

namespace App\Enums;

enum InterclubResult: string
{
    case DRAW = 'Draw';
    case FORFEIT_LOSS = 'ForfeitLoss';
    case FORFEIT_WIN = 'ForfeitWin';
    case LOSS = 'Loss';
    case WIN = 'Win';
    case WITHDRAWAL = 'Withdrawal';
    case WITHDRAWAL_OPPONENT = 'WithdrawalOpponent';
}
