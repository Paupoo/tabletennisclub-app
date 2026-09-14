<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ceux qui soutiennent le club, lus au même endroit par tout le site.
 *
 * La page d'accueil et la carte du bar affichent les mêmes logos : la liste
 * vivait en dur dans le contrôleur de la home, hors de portée de toute autre
 * vue. Le premier sponsor ajouté aurait manqué sur l'écran du bar, et personne
 * ne s'en serait aperçu avant lui.
 */
class Sponsors
{
    /**
     * @return list<array{name: string, logo: string|null, url: string|null}>
     */
    public static function all(): array
    {
        /** @var list<array{name: string, logo?: string|null, url?: string|null}> $sponsors */
        $sponsors = config('club.sponsors', []);

        return array_map(static fn (array $sponsor): array => [
            'name' => $sponsor['name'],
            'logo' => isset($sponsor['logo']) ? asset($sponsor['logo']) : null,
            'url' => $sponsor['url'] ?? null,
        ], $sponsors);
    }
}
