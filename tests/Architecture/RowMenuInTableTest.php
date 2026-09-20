<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * <x-admin.shared.row-menu> ouvre son panneau en `lg:absolute`. Mary enveloppe
 * une <x-table> dans `overflow-x-auto` (Table.php), ce qui ouvre un contexte de
 * rognage et coupe le panneau ouvert. Neuf écrans le faisaient avant qu'on s'en
 * aperçoive, et aucun test Livewire ne pouvait le voir : le markup est le même
 * qu'il s'affiche ou non.
 *
 * Piège CSS au passage : `overflow-x-auto` seul laisse `overflow-y: visible`,
 * que la spec recalcule alors en `auto` — ça rogne donc dans les deux
 * directions, pas seulement horizontalement.
 *
 * D'où la règle : une table qui porte des actions de ligne rend son conteneur
 * transparent à partir de lg, où elle tient dans sa boîte. En dessous, le
 * panneau est une feuille du bas en `fixed`, qu'`overflow` ne retient pas.
 */
const REQUIRED_CONTAINER = 'container-class="overflow-x-auto lg:overflow-x-visible"';

it('lets a table hand the row menu its way out', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    foreach ((new Finder)->files()->in($viewsPath)->name('*.blade.php') as $file) {
        $source = (string) file_get_contents($file->getPathname());

        if (! str_contains($source, 'x-admin.shared.row-menu') || ! str_contains($source, '<x-table')) {
            continue;
        }

        if (! str_contains($source, REQUIRED_CONTAINER)) {
            $offenders[] = str_replace($viewsPath . '/', '', $file->getPathname());
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], sprintf(
        "Ces tables rognent le panneau qu'elles ouvrent. Déclarez\n%s\nsur la <x-table> :\n\n%s\n",
        REQUIRED_CONTAINER,
        implode("\n", $offenders),
    ));
});
