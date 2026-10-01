<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Who the federation says will not play a fixture, read from our side.
 *
 * A forfeit cancels one evening; a withdrawal takes a team out of the division
 * for the rest of the season, and TabT then flags every remaining fixture of
 * that team with both forfeit flags at once — which is why the withdrawal is
 * read first and the forfeit flags alone never tell the two apart.
 */
enum InterclubForfeit: string
{
    case OPPONENT_FORFEIT = 'OpponentForfeit';
    case OPPONENT_WITHDRAWAL = 'OpponentWithdrawal';
    case OUR_FORFEIT = 'OurForfeit';
    case OUR_WITHDRAWAL = 'OurWithdrawal';

    /**
     * Whether it is our own team that will not play.
     */
    public function isOurs(): bool
    {
        return $this === self::OUR_FORFEIT || $this === self::OUR_WITHDRAWAL;
    }

    /**
     * The result the forfeit gives our side once the federation has locked it.
     */
    public function verdict(): InterclubResultEnum
    {
        return match ($this) {
            self::OPPONENT_FORFEIT => InterclubResultEnum::FORFEIT_WIN,
            self::OPPONENT_WITHDRAWAL => InterclubResultEnum::WITHDRAWAL_OPPONENT,
            self::OUR_FORFEIT => InterclubResultEnum::FORFEIT_LOSS,
            self::OUR_WITHDRAWAL => InterclubResultEnum::WITHDRAWAL,
        };
    }
}
