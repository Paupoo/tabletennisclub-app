<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Ce que l'article C.22 permet de dire d'un joueur qu'on s'apprête à aligner.
 *
 * Trois états, et un quatrième qui dit qu'on ne se prononce pas. L'incertitude
 * est une réponse à part entière : le règlement se lit sur le troisième joueur
 * *ayant effectivement joué*, que personne ne connaît avant la rencontre, donc
 * il y a des cas où la seule réponse honnête est « ça dépendra ».
 */
enum LineupLegality: string
{
    case ALLOWED = 'allowed';
    case FORBIDDEN = 'forbidden';
    case NOT_APPLICABLE = 'not_applicable';
    case UNCERTAIN = 'uncertain';

    /**
     * La variante douce, comme {@see InterclubAvailability::color()} : daisyUI
     * associe au badge plein sa couleur `-content`, mesurée sous le seuil AA.
     */
    public function color(): string
    {
        return match ($this) {
            self::ALLOWED => 'badge-success badge-soft',
            self::UNCERTAIN => 'badge-warning badge-soft',
            self::FORBIDDEN => 'badge-error badge-soft',
            self::NOT_APPLICABLE => 'badge-ghost',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ALLOWED => __('Can be lined up'),
            self::UNCERTAIN => __('To be checked'),
            self::FORBIDDEN => __('Cannot be lined up'),
            self::NOT_APPLICABLE => __('Rule not applicable'),
        };
    }
}
