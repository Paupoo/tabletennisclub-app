<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum TrainingType: string
{
    case DIRECTED = 'Directed';
    case FREE = 'Free';
    case SUPERVISED = 'Supervised';

    /**
     * Le libellé affiché, traduit.
     *
     * La `value` reste l'anglais : elle est stockée en base et sert de clé au
     * code couleur de l'horaire public. Seul l'affichage passe par ici.
     */
    public function label(): string
    {
        return match ($this) {
            self::DIRECTED => __('Directed'),
            self::FREE => __('Free'),
            self::SUPERVISED => __('Supervised'),
        };
    }
}
