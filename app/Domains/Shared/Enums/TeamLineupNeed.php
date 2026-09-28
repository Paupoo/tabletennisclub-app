<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Ce qu'il manque à une équipe pour une journée, vu du sélectionneur.
 *
 * Le brouillon compte comme coché : une composition faite mais pas envoyée n'est
 * pas un manque, c'est un envoi en retard.
 */
enum TeamLineupNeed: string
{
    /** Autant de joueurs cochés que de places. */
    case COMPLETE = 'complete';

    /** Il manque du monde, mais les oui et peut-être libres de l'équipe suffisent. */
    case COVERABLE = 'coverable';

    /** Il manque du monde, et l'équipe ne peut pas se couvrir seule. */
    case UNCOVERED = 'uncovered';
}
