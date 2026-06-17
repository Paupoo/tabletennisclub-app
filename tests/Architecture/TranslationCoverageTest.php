<?php

declare(strict_types=1);

namespace tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

$basePath = dirname(__DIR__, 2);

/**
 * Extracts all __() keys used in app/ and resources/views/.
 *
 * @return string[]
 */
function extractTranslationKeysFromCodebase(): array
{
    $base = dirname(__DIR__, 2);
    $keys = [];

    foreach ([$base . '/resources/views', $base . '/app'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/__\('([^']*)'\)|__\(\"([^\"]*)\"\)/",
                file_get_contents($file->getPathname()),
                $matches,
            );

            array_push($keys, ...array_filter(array_merge($matches[1], $matches[2])));
        }
    }

    return array_values(array_unique($keys));
}

test('fr_BE and nl_BE have the same translation keys', function () use ($basePath) {
    $frKeys = array_keys(json_decode(file_get_contents($basePath . '/lang/fr_BE.json'), associative: true));
    $nlKeys = array_keys(json_decode(file_get_contents($basePath . '/lang/nl_BE.json'), associative: true));

    $missingInNl = array_diff($frKeys, $nlKeys);
    $missingInFr = array_diff($nlKeys, $frKeys);

    expect($missingInNl)->toBeEmpty(
        count($missingInNl) . " key(s) in fr_BE.json missing from nl_BE.json:\n- " . implode("\n- ", $missingInNl),
    );

    expect($missingInFr)->toBeEmpty(
        count($missingInFr) . " key(s) in nl_BE.json missing from fr_BE.json:\n- " . implode("\n- ", $missingInFr),
    );
});

test('all __() strings in the codebase are translated in fr_BE.json', function () use ($basePath) {
    $translated = json_decode(file_get_contents($basePath . '/lang/fr_BE.json'), associative: true);

    $missing = array_values(array_filter(
        extractTranslationKeysFromCodebase(),
        fn (string $key) => ! array_key_exists($key, $translated),
    ));

    expect($missing)->toBeEmpty(
        count($missing) . " __() key(s) missing from fr_BE.json:\n- " . implode("\n- ", $missing),
    );
});

test('all __() strings in the codebase are translated in nl_BE.json', function () use ($basePath) {
    $translated = json_decode(file_get_contents($basePath . '/lang/nl_BE.json'), associative: true);

    $missing = array_values(array_filter(
        extractTranslationKeysFromCodebase(),
        fn (string $key) => ! array_key_exists($key, $translated),
    ));

    expect($missing)->toBeEmpty(
        count($missing) . " __() key(s) missing from nl_BE.json:\n- " . implode("\n- ", $missing),
    );
});
