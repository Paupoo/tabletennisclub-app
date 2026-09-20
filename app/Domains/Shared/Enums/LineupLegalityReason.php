<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Pourquoi le verdict C.22 est ce qu'il est.
 *
 * Deux incertitudes se ressemblent à l'écran et ne se règlent pas du tout de la
 * même façon : une composition que l'équipe supérieure n'a pas encore faite se
 * débloque par un coup de fil à son capitaine, un indice de référence manquant
 * se débloque en recalculant la liste des forces. D'où deux motifs distincts
 * plutôt qu'un « à vérifier » unique.
 */
enum LineupLegalityReason: string
{
    case MISSING_REFERENCE_INDEX = 'missing_reference_index';
    case NO_SUPERIOR_TEAM = 'no_superior_team';
    case STRONGER_THAN_THRESHOLD = 'stronger_than_threshold';
    case SUPERIOR_LINEUP_UNKNOWN = 'superior_lineup_unknown';
    case TEAM_RANK_UNKNOWN = 'team_rank_unknown';
    case WEAKER_THAN_THRESHOLD = 'weaker_than_threshold';

    /**
     * La phrase que lit le capitaine. Elle nomme ce qu'il peut faire, pas l'état
     * interne : « appelez le capitaine de l'équipe supérieure » est actionnable,
     * « seuil indéterminé » ne l'est pas.
     */
    public function label(): string
    {
        return match ($this) {
            self::MISSING_REFERENCE_INDEX => __('Reference index missing — check the force list before lining up'),
            self::NO_SUPERIOR_TEAM => __('No superior team in this category'),
            self::STRONGER_THAN_THRESHOLD => __('Too strong for this team under rule C.22'),
            self::SUPERIOR_LINEUP_UNKNOWN => __('Depends on a lineup the superior team has not composed yet'),
            self::TEAM_RANK_UNKNOWN => __('Team order cannot be read from the team names — rule not checked'),
            self::WEAKER_THAN_THRESHOLD => __('Allowed whatever the superior team lines up'),
        };
    }
}
