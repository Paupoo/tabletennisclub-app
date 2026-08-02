<?php

declare(strict_types=1);

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
    ->withSkip([
        RemoveNullArgOnNullDefaultParamRector::class,
        RemoveNullNamedArgOnNullDefaultParamRector::class,
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(68)
    ->withCodeQualityLevel(0);
