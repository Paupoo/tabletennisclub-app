<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\CompactToVariablesRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\MethodCall\RemoveNullArgOnNullDefaultParamRector;
use Rector\DeadCode\Rector\MethodCall\RemoveNullNamedArgOnNullDefaultParamRector;

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
     * Ces deux règles suppriment un argument `null` que la signature reprend par
     * défaut. C'est vrai, et c'est précisément ce qu'il ne faut pas faire ici :
     * la moitié de nos tests passent ce null pour l'éprouver. `->set('gender')`
     * ne dit plus quelle valeur est mise, « handles null values properly » perd
     * ses valeurs nulles, et le commentaire « null, not [] — passing [] there
     * would strip them » de UserObserverTest se met à commenter un argument
     * disparu. Un test qui ne montre plus son entrée ne prouve plus rien.
     */
    /*
     * Et celle-ci déplie `compact()` en tableau explicite. L'argument est bon en
     * théorie — un tableau se relie à ses variables pour l'analyse statique là où
     * compact() ne le fait pas — mais Pint ne coupe pas les lignes : les dix clés
     * du tableau de DashboardController sortent sur 300 caractères. On échange une
     * lisibilité réelle contre une vérifiabilité que PHPStan ne nous réclame pas.
     */
    ->withSkip([
        // Généré par `artisan package:discover` et hors index. Rector y ajoutait
        // un declare(strict_types=1) que la prochaine génération effacerait.
        __DIR__ . '/bootstrap/cache',
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
        RemoveNullArgOnNullDefaultParamRector::class,
        RemoveNullNamedArgOnNullDefaultParamRector::class,
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(68)
    ->withCodeQualityLevel(76);
