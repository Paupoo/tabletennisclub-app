<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\CompactToVariablesRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveReturnTagIncompatibleWithNativeTypeRector;
use Rector\DeadCode\Rector\MethodCall\RemoveNullArgOnNullDefaultParamRector;
use Rector\DeadCode\Rector\MethodCall\RemoveNullNamedArgOnNullDefaultParamRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\TypeDeclaration\Rector\Closure\ClosureReturnTypeRector;
use Rector\TypeDeclaration\Rector\FuncCall\AddArrayFunctionClosureParamTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap',
        __DIR__ . '/config',
        __DIR__ . '/resources',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    /*
     * Les règles qui ajoutent un type écrivent le nom complet en dur, y compris
     * quand la classe est déjà importée deux lignes plus haut. Sans ceci, gagner
     * un type de retour coûte un `\App\Domains\Competitions\Interclub\Models\
     * Interclub` au milieu d'une closure. Les docblocks sont laissés tranquilles :
     * ils portent des formes (`object{...}`, `array<int, string>`) qu'il n'y a
     * rien à importer.
     */
    ->withImportNames(importDocBlockNames: false, importShortClasses: false)
    /*
     * Chaque exclusion ci-dessous porte la raison qui l'a motivée : une règle
     * écartée sans justification finit par être remise « pour voir », et le
     * problème qu'elle causait est redécouvert à ce moment-là.
     */
    ->withSkip([
        // Généré par `artisan package:discover` et hors index. Rector y ajoutait
        // un declare(strict_types=1) que la prochaine génération effacerait.
        __DIR__ . '/bootstrap/cache',
        /*
         * Déplie `compact()` en tableau explicite. L'argument est bon en théorie
         * — un tableau se relie à ses variables pour l'analyse statique là où
         * compact() ne le fait pas — mais Pint ne coupe pas les lignes : les dix
         * clés du tableau de DashboardController sortent sur 300 caractères. On
         * échangerait une lisibilité réelle contre une vérifiabilité que PHPStan
         * ne nous réclame pas.
         */
        CompactToVariablesRector::class,
        /*
         * Remplace `$x !== null` par `$x instanceof \Nom\Complet\Qualifié`, y
         * compris là où la classe est déjà importée. Trois pertes sèches : un
         * FQCN en dur, une double négation (`if (! $g instanceof Guardian)` pour
         * dire « pas de tuteur »), et un test de nullité devenu un test de classe
         * concrète — `$row->birthdate !== null` cesse d'être vrai le jour où la
         * propriété passe à CarbonInterface.
         */
        FlipTypeControlToUseExclusiveTypeRector::class,
        // Réordonne les arguments nommés sur l'ordre des paramètres. Sur nos
        // mailables, huit fichiers changent pour un déplacement de virgule.
        SortCallLikeNamedArgsRector::class,
        /*
         * Ces deux-là suppriment un argument `null` que la signature reprend par
         * défaut. C'est exact, et c'est précisément ce qu'il ne faut pas faire
         * ici : la moitié de nos tests passent ce null pour l'éprouver.
         * `->set('gender')` ne dit plus quelle valeur est mise, un test nommé
         * « handles null values properly » perd ses valeurs nulles, et le
         * commentaire « null, not [] — passing [] there would strip them » de
         * UserObserverTest se met à commenter un argument disparu. Un test qui
         * ne montre plus son entrée ne prouve plus rien.
         */
        RemoveNullArgOnNullDefaultParamRector::class,
        RemoveNullNamedArgOnNullDefaultParamRector::class,
        /*
         * Ces deux-là marchent ensemble et se soldent par une perte. La première
         * resserre `: object` en `: \stdClass` ; la seconde en déduit que le
         * `@return object{id: int, name: string, ...}` de roster.php ne colle plus
         * au type natif et le supprime. On échangerait douze champs décrits contre
         * un `stdClass` qui ne dit rien — PHPStan comme le lecteur y perdent.
         */
        NarrowObjectReturnTypeRector::class,
        RemoveReturnTagIncompatibleWithNativeTypeRector::class,
        /*
         * Ici seulement. Rector déduit `string` du seul `$this->announcements[] = ''`
         * et ne voit pas que la propriété est aussi rechargée depuis une colonne JSON.
         * Le corps de la closure est `filled($v)`, qui accepte n'importe quoi : le type
         * ne vérifie rien de plus et transformerait un null hérité en TypeError.
         */
        AddArrayFunctionClosureParamTypeRector::class => [
            __DIR__ . '/resources/views/pages/club-events/meetings/⚡minutes/minutes.php',
        ],
        /*
         * Pas dans les tests. `expect()` déclare rendre Pest\Mixins\Expectation,
         * la classe qui porte l'autocomplétion, et rend Pest\Expectation à
         * l'exécution : typer `fn (): Expectation => expect(...)` a fait tomber
         * 70 tests de policy sur une TypeError. Le type ne servait rien non plus
         * — une closure de test n'est appelée par aucun code typé.
         */
        AddArrowFunctionReturnTypeRector::class => [__DIR__ . '/tests'],
        ClosureReturnTypeRector::class => [__DIR__ . '/tests'],
    ])
    ->withTypeCoverageLevel(73)
    ->withDeadCodeLevel(68)
    ->withCodeQualityLevel(76);
