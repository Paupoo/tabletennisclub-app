<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * Règle DS-B du design system : rien sous `text-xs` (12 px), et aucune taille
 * arbitraire `text-[Npx]`. Les deux étaient enfreintes au même endroit — 129
 * déclarations, jusqu'à `text-[9px]` sur le libellé « Set » d'un écran de
 * saisie de score utilisé debout, dans une salle de sport.
 *
 * Une taille arbitraire échappe à l'échelle : elle ne suit ni le rythme
 * typographique ni un éventuel réglage global. Et sous 12 px, un chiffre lu
 * vite se devine plutôt qu'il ne se lit.
 *
 * Le bar est hors périmètre : layout autonome, CSS écrite à la main, aucun
 * composant ni classe partagés avec l'application.
 */
it('never sizes text below the 12px floor, nor off the scale', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    $files = (new Finder)
        ->files()
        ->in($viewsPath)
        ->exclude('bar')
        ->name('*.blade.php');

    foreach ($files as $file) {
        $source = (string) file_get_contents($file->getPathname());

        preg_match_all('/text-\[(\d+)px\]/', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as [$whole, $pixels]) {
            $offenders[] = sprintf(
                '%s:%d  %s',
                str_replace($viewsPath . '/', '', $file->getPathname()),
                substr_count(substr($source, 0, $whole[1]), "\n") + 1,
                $whole[0],
            );
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], sprintf(
        "Ces tailles sortent de l'échelle Tailwind, et la plupart passent sous le\n"
        . "plancher de 12 px que pose DS-B. Utilise text-xs (12), text-sm (14) ou\n"
        . "text-base (16) :\n\n%s\n",
        implode("\n", $offenders),
    ));
});
