<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * La vitrine posait ses couleurs en dur — `bg-white`, `text-gray-600`,
 * `border-gray-200` — pendant que les gris qu'elle demandait étaient déjà
 * ramenés par `app.css` vers une couleur calculée depuis `base-content`. Le
 * texte suivait donc le thème et les surfaces non, ce qui donnait du gris clair
 * calculé posé sur du blanc resté blanc : le titre de la feuille de score a
 * mesuré 1,02:1 sans qu'aucun test ne bronche.
 *
 * Les jetons existent et tournent déjà dans le back-office. Cette règle empêche
 * simplement le dialecte précédent de revenir, une PR à la fois.
 *
 * Le PHP est dans le périmètre, et ce n'est pas un excès de zèle : deux `match`
 * d'`app/` retournent des classes de couleur, et c'est pour ça qu'`app.css`
 * scanne `app/`. Un contrôle limité aux `.blade.php` les laisserait passer.
 */
$forbidden = [
    'bg-white' => 'bg-base-100',
    'bg-gray-50' => 'bg-base-200',
    'bg-gray-100' => 'bg-base-200',
    'text-gray-900' => 'text-base-content',
    'text-gray-800' => 'text-base-content',
    'text-gray-700' => 'text-muted',
    'text-gray-600' => 'text-muted',
    'text-gray-500' => 'text-subtle',
    'text-gray-400' => 'text-subtle',
    'text-gray-300' => 'text-subtle',
    'border-gray-100' => 'border-base-300',
    'border-gray-200' => 'border-base-300',
    'border-gray-300' => 'border-base-300',
    'text-black' => 'text-base-content',
];

/*
 * Trois exceptions, et chacune dit pourquoi elle en est une.
 *
 * Le pied de page et les tuiles sponsors sont sombres par construction : leurs
 * couleurs ne suivent pas le thème, elles en fournissent une. Le bandeau
 * cookies porte un aplat de marque dans les deux thèmes. Une exception se
 * déclare ici, à la ligne près, pour qu'elle se relise — pas au fil du code.
 */
$allowed = [
    'components/public/footer.blade.php' => [
        1,   // <footer> : la surface sombre elle-même, désormais portée par --color-footer
    ],
    'components/public/sponsors-section.blade.php' => [
        16, 30, // tuiles bg-gray-800 : un fond stable pour des logos, dans les deux thèmes
    ],
    'components/public/navigation.blade.php' => [
        37, 45, 71, 74, // encre des boutons posés sur bg-club-yellow, jaune dans les deux thèmes
    ],
];

/*
 * Un `border` sans classe de couleur ne dessine pas « la bordure par défaut » :
 * `app.css` fixe `border-color: var(--color-gray-200)` sur tout élément, un gris
 * clair qui ne bouge dans aucun thème. Sur une page sombre, cela donne un filet
 * lumineux — celui qui traversait la largeur du site juste au-dessus du pied de
 * page, et quatre autres ailleurs.
 *
 * La bordure est donc soit nommée, soit absente ; il n'y a pas de troisième cas.
 */
it('never draws a border without saying which colour', function (): void {
    $root = dirname(__DIR__, 2);

    $files = (new Finder)
        ->files()
        ->in([
            $root . '/resources/views/components/public',
            $root . '/resources/views/public',
            $root . '/resources/views/livewire/public',
        ])
        ->name('*.blade.php');

    $bare = '/\bborder(?:-[tbrlxy])?(?![-\w])/';
    $coloured = '/\bborder-(base|primary|secondary|info|warning|error|success|club|white|black|gray|slate|neutral|zinc|blue|red|amber|green|purple|orange|emerald|indigo|pink|teal|transparent|current|inherit|\[)/';

    $offenders = [];

    foreach ($files as $file) {
        $lines = explode("\n", (string) file_get_contents($file->getPathname()));

        foreach ($lines as $index => $line) {
            if (preg_match($bare, $line) !== 1 || preg_match($coloured, $line) === 1) {
                continue;
            }

            // Une couleur passée par variable est nommée ailleurs, en PHP.
            if (str_contains($line, '$style[') || str_contains($line, "['border']")) {
                continue;
            }

            $offenders[] = sprintf(
                '%s:%d  %s',
                str_replace($root . '/resources/views/', '', $file->getPathname()),
                $index + 1,
                trim($line),
            );
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], sprintf(
        "Ces bordures ne disent pas leur couleur, elles héritent donc du gris clair que\n"
        . "pose le reset — un filet lumineux sur une page sombre. Nomme-la (border-base-300)\n"
        . "ou retire la bordure :\n\n%s\n",
        implode("\n", $offenders),
    ));
});

it('keeps the public views speaking the theme vocabulary', function () use ($forbidden, $allowed): void {
    $root = dirname(__DIR__, 2);

    $scopes = [
        $root . '/resources/views/components/public',
        $root . '/resources/views/public',
        $root . '/resources/views/livewire/public',
        $root . '/resources/views/layouts/guest.blade.php',
        $root . '/app/Livewire/Public',
        $root . '/app/Domains/ClubPosts',
    ];

    $dirs = array_values(array_filter($scopes, 'is_dir'));
    $singles = array_values(array_filter($scopes, 'is_file'));

    $files = [];
    if ($dirs !== []) {
        foreach ((new Finder)->files()->in($dirs)->name('*.php') as $file) {
            $files[] = $file->getPathname();
        }
    }
    $files = array_merge($files, $singles);
    sort($files);

    $offenders = [];

    foreach ($files as $path) {
        $relative = str_replace([$root . '/resources/views/', $root . '/'], '', $path);
        $source = (string) file_get_contents($path);

        foreach ($forbidden as $class => $replacement) {
            preg_match_all('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$needle, $offset]) {
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;

                if (in_array($line, $allowed[$relative] ?? [], true)) {
                    continue;
                }

                $offenders[] = sprintf('%s:%d  %s  →  %s', $relative, $line, $class, $replacement);
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], sprintf(
        "Ces couleurs sont posées en dur : elles resteront claires quand la page passera\n"
        . "en thème sombre, alors que le texte au-dessus, lui, aura suivi. Les jetons à\n"
        . "droite sont ceux qu'emploie déjà le back-office.\n\n"
        . "Une surface volontairement sombre dans les deux thèmes se déclare en exception\n"
        . "dans ce fichier, avec sa raison.\n\n%s\n",
        implode("\n", $offenders),
    ));
});
