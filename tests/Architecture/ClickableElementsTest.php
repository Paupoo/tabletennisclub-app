<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * Fiche KB-1 de l'audit UX. Quinze commandes étaient écrites en
 * <div wire:click> ou <div @click> : Tab passait par-dessus, Entrée et Espace
 * ne déclenchaient rien, un lecteur d'écran les annonçait comme du texte. Échec
 * du critère WCAG 2.2 nº 2.1.1 « Clavier », niveau A.
 *
 * axe-core ne sait pas voir ce défaut — il faudrait décider si une autre
 * commande offre la même action, ce qu'aucun test automatique ne tranche. D'où
 * ce garde statique : un élément qui réagit au clic est un <button>, un <a> ou
 * un champ de formulaire. Le navigateur fournit alors le focus, les touches et
 * l'annonce du rôle sans une ligne de code.
 *
 * Deux exceptions, toutes deux vraies :
 *   - les voiles de fond (`inset-0`), qui ne font que refermer une feuille déjà
 *     refermable autrement ;
 *   - les modificateurs de renvoi (`.outside`, `.away`, `.self`), qui écoutent
 *     un clic ailleurs et ne sont donc pas des commandes.
 */

/** Balises HTML nues : un composant, lui, peut très bien rendre un <button>. */
const INERT_TAGS = ['div', 'span', 'li', 'p', 'section', 'article', 'header', 'footer', 'td', 'tr', 'nav', 'main', 'aside'];

it('never hangs an action on something the keyboard cannot reach', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    foreach ((new Finder)->files()->in($viewsPath)->name('*.blade.php') as $file) {
        $source = (string) file_get_contents($file->getPathname());

        preg_match_all(
            '/<(' . implode('|', INERT_TAGS) . ')((?:[^<>"\']|"[^"]*"|\'[^\']*\')*?)>/s',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
        );

        foreach ($matches as [$tag, $name, $attributes]) {
            if (! preg_match('/(?<![\w:-])(@click|wire:click|x-on:click)([\w.-]*)/', $attributes[0], $handler)) {
                continue;
            }

            // Un clic capté ailleurs ferme un panneau ; ce n'est pas une commande.
            if (preg_match('/\.(outside|away|self)\b/', $handler[2])) {
                continue;
            }

            // Un voile de fond ne double jamais qu'une sortie déjà accessible.
            if (str_contains($attributes[0], 'inset-0')) {
                continue;
            }

            // Rendu atteignable autrement — un rôle et une place dans l'ordre de tabulation.
            if (preg_match('/(?<![\w:-])(tabindex|role)\s*=/', $attributes[0])) {
                continue;
            }

            $offenders[] = sprintf(
                '%s:%d  <%s %s…>',
                str_replace($viewsPath . '/', '', $file->getPathname()),
                substr_count(substr($source, 0, $tag[1]), "\n") + 1,
                $name[0],
                trim($handler[0]),
            );
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], sprintf(
        "Ces commandes ne sont atteignables qu'à la souris. Un <button type=\"button\"> fait\n"
        . "le même travail et se laisse atteindre au Tab :\n\n%s\n",
        implode("\n", $offenders),
    ));
});
