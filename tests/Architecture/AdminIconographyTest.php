<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * Règle d'iconographie du design system : l'emoji est un accent du site public,
 * le back-office parle en Heroicons outline. Le dépôt tient 420 icônes outline
 * et zéro solide ; neuf emoji traînaient encore dans les écrans de tournoi et
 * d'inscription, dont un « ✓ » collé au libellé d'un bouton.
 *
 * Les emoji passés en `icon="…"` à un composant d'événement de club ne sont pas
 * du chrome d'admin : c'est une donnée stockée puis affichée sur le site public,
 * là où le design system les autorise. Le motif `icon="…"` est donc réservé.
 *
 * Le bar est hors périmètre : layout autonome, CSS écrite à la main, aucun
 * composant ni classe partagés avec l'application.
 */
it('draws the back office with icons, never with emoji', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    /*
     * Pictogrammes et dingbats employés comme icônes. Les flèches typographiques
     * (`→`, `←`) en sont exclues : ce sont des signes de ponctuation de la copie
     * française, pas des icônes.
     */
    $glyphs = '\x{1F300}-\x{1FAFF}\x{2700}-\x{27BF}\x{2B00}-\x{2BFF}\x{26A0}-\x{26FF}';

    $offenders = [];

    $files = (new Finder)
        ->files()
        ->in([$viewsPath . '/components/admin', $viewsPath . '/pages'])
        ->name('*.blade.php');

    foreach ($files as $file) {
        // Un commentaire Blade ne s'affiche pas : il documente souvent la barre
        // qu'il décrit (« Mobile : 🔍 · filtre · ☰ ») et n'est pas du chrome. Il
        // est vidé de son contenu, mais garde ses sauts de ligne pour que les
        // numéros signalés restent ceux du fichier.
        $source = preg_replace_callback(
            '/\{\{--.*?--\}\}/su',
            fn (array $comment): string => str_repeat("\n", substr_count($comment[0], "\n")),
            (string) file_get_contents($file->getPathname()),
        );

        foreach (explode("\n", (string) $source) as $number => $line) {
            // Charge utile d'un événement public, pas du chrome d'admin.
            if (preg_match('/icon\s*=\s*"[^"]*[' . $glyphs . ']/u', $line)) {
                continue;
            }

            // Le ⚡ des noms de dossiers Livewire est une convention du dépôt,
            // pas une icône : il n'apparaît que dans un identifiant de vue.
            $line = preg_replace('~[./]\x{26A1}~u', '', $line);

            if (preg_match('/[' . $glyphs . ']/u', $line, $found)) {
                $offenders[] = sprintf(
                    '%s:%d  %s',
                    str_replace($viewsPath . '/', '', $file->getPathname()),
                    $number + 1,
                    $found[0],
                );
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], sprintf(
        "Le back-office dessine en Heroicons outline, pas en emoji. Remplace par\n"
        . "l'icône correspondante (o-lock-closed, o-trophy, o-check) :\n\n%s\n",
        implode("\n", $offenders),
    ));
});
