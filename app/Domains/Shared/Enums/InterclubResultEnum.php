<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum InterclubResultEnum: string
{
    case DRAW = 'Draw';
    case FORFEIT_LOSS = 'ForfeitLoss';
    case FORFEIT_WIN = 'ForfeitWin';
    case LOSS = 'Loss';
    case WIN = 'Win';
    case WITHDRAWAL = 'Withdrawal';
    case WITHDRAWAL_OPPONENT = 'WithdrawalOpponent';

    /**
     * Whether the tie went against us, counting our own forfeit.
     */
    public function isLoss(): bool
    {
        return in_array($this, [self::LOSS, self::FORFEIT_LOSS, self::WITHDRAWAL], true);
    }

    /**
     * Whether the score tells the tie: a forfeit is read in words instead.
     */
    public function isPlayed(): bool
    {
        return in_array($this, [self::WIN, self::LOSS, self::DRAW], true);
    }

    /**
     * Whether the tie went our way, counting a forfeit by either side.
     */
    public function isWin(): bool
    {
        return in_array($this, [self::WIN, self::FORFEIT_WIN, self::WITHDRAWAL_OPPONENT], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::WIN => __('Win'),
            self::LOSS => __('Loss'),
            self::DRAW => __('Draw'),
            self::FORFEIT_WIN => __('Win by forfeit'),
            self::FORFEIT_LOSS => __('Loss by forfeit'),
            self::WITHDRAWAL => __('Our general forfeit'),
            self::WITHDRAWAL_OPPONENT => __('Opponent general forfeit'),
        };
    }
}
