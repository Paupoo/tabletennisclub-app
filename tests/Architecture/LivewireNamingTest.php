<?php

declare(strict_types=1);

/**
 * Une propriété publique et une méthode publique ne peuvent pas porter le même
 * nom dans un composant Livewire.
 *
 * PHP l'accepte sans broncher — `$this->search` lit la propriété, `$this->search()`
 * appelle la méthode — et les tests PHP passent donc tous. Mais le proxy `$wire`
 * côté navigateur n'a qu'un espace de noms : la propriété masque la méthode, et
 * `$wire.search(value)` tente d'appeler une chaîne. Rien ne se produit, aucune
 * erreur ne remonte jusqu'à PHP.
 *
 * Vécu le 2026-09-11 : un `<x-choices searchable>` posé dans la liste des équipes,
 * dont le composant portait déjà `public string $search` pour filtrer ses cartes.
 * La liste initiale s'affichait, la recherche était morte, et toute la suite PHP
 * restait verte.
 */
it('never gives a Livewire component a method and a property of the same name', function (): void {
    $components = [];

    $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/resources/views'));

    foreach ($directory as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        if (str_contains($file->getFilename(), '.blade.') || ! str_contains($file->getPathname(), '⚡')) {
            continue;
        }

        $components[] = $file->getPathname();
    }

    expect($components)->not->toBeEmpty('Aucun composant ⚡ trouvé : le test se croirait vert.');

    $offenders = [];

    foreach ($components as $path) {
        $source = file_get_contents($path) ?: '';

        preg_match_all('/^\s*public\s+(?:\??[\w\\\\|]+\s+)?\$(\w+)/m', $source, $properties);
        preg_match_all('/^\s*public\s+function\s+(\w+)\s*\(/m', $source, $methods);

        $clash = array_intersect($properties[1] ?? [], $methods[1] ?? []);

        if ($clash !== []) {
            $offenders[] = str_replace(dirname(__DIR__, 2) . '/', '', $path) . ' → ' . implode(', ', $clash);
        }
    }

    expect($offenders)->toBeEmpty(
        count($offenders) . " composant(s) où une propriété masque une méthode dans \$wire :\n- "
        . implode("\n- ", $offenders)
    );
});
