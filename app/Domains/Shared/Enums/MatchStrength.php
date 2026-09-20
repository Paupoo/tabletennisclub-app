<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Ce qu'un rapprochement vaut, du point de vue du trésorier qui le confirme.
 *
 * Jamais « certain » : le barème repose sur des heuristiques de noms qui
 * produisent des faux positifs sur les patronymes courants. Ces libellés
 * décrivent un signal, ils ne promettent pas un résultat — c'est le trésorier
 * qui tranche, et aucun niveau ne présélectionne la ligne.
 */
enum MatchStrength: string
{
    case NONE = 'none';
    case STRONG = 'strong';
    case TO_VERIFY = 'to_verify';
    case WEAK = 'weak';

    public function getLabel(): string
    {
        return match ($this) {
            self::STRONG => __('Strong match'),
            self::TO_VERIFY => __('To check'),
            self::WEAK => __('Weak lead'),
            self::NONE => '',
        };
    }

    /** Poids de tri : la ligne la plus probable remonte en tête de liste. */
    public function rank(): int
    {
        return match ($this) {
            self::STRONG => 3,
            self::TO_VERIFY => 2,
            self::WEAK => 1,
            self::NONE => 0,
        };
    }
}
