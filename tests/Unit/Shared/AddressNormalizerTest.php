<?php

declare(strict_types=1);

use App\Domains\Shared\Support\AddressNormalizer;

it('title-cases an address the federation exported in capitals', function (): void {
    expect(AddressNormalizer::titleCase('RUE DU TEST 13'))->toBe('Rue du Test 13');
});

/*
 * The club's own commune. Plain title casing yields `Louvain-La-Neuve`, which
 * would be wrong on nearly every member's file and on every address label
 * printed from one.
 */
it('keeps a particle lowercase across a hyphen', function (string $exported, string $expected): void {
    expect(AddressNormalizer::titleCase($exported))->toBe($expected);
})->with([
    ['LOUVAIN-LA-NEUVE', 'Louvain-la-Neuve'],
    ['OTTIGNIES-LOUVAIN-LA-NEUVE', 'Ottignies-Louvain-la-Neuve'],
    ['BRAINE-LE-COMTE', 'Braine-le-Comte'],
    ['VILLERS-LA-VILLE', 'Villers-la-Ville'],
]);

/*
 * A particle opening the name is the name's own first word, not a particle.
 */
it('capitalises a particle that starts the name', function (string $exported, string $expected): void {
    expect(AddressNormalizer::titleCase($exported))->toBe($expected);
})->with([
    ['LA HULPE', 'La Hulpe'],
    ['LE ROEULX', 'Le Roeulx'],
    ["L'ECLUSE", "L'Ecluse"],
]);

it('reads an elided particle as the two words it is', function (string $exported, string $expected): void {
    expect(AddressNormalizer::titleCase($exported))->toBe($expected);
})->with([
    ["AVENUE DE L'EXEMPLE 4", "Avenue de l'Exemple 4"],
    ["BRAINE-L'ALLEUD", "Braine-l'Alleud"],
]);

/*
 * `ucfirst(strtolower())` would give `12a`. A house number is not a word.
 */
it('leaves a house number alone', function (): void {
    expect(AddressNormalizer::titleCase('CHAUSSEE DE BRUXELLES 12A'))->toBe('Chaussee de Bruxelles 12A');
});

it('keeps a word that is not a particle capitalised', function (): void {
    expect(AddressNormalizer::titleCase('MONT-SAINT-GUIBERT'))->toBe('Mont-Saint-Guibert');
});

/*
 * A lone letter after the house number is a box, not the elided particle it
 * looks like. Lowercasing it would name a different door.
 */
it('leaves a box letter alone', function (string $exported, string $expected): void {
    expect(AddressNormalizer::titleCase($exported))->toBe($expected);
})->with([
    ['RUE DU TEST 12 A', 'Rue du Test 12 A'],
    ['RUE DU TEST 13 D', 'Rue du Test 13 D'],
    ['RUE DU TEST 14 L', 'Rue du Test 14 L'],
]);

it('collapses runs of spaces', function (): void {
    expect(AddressNormalizer::titleCase('RUE  DU   TEST  13'))->toBe('Rue du Test 13');
});

/*
 * The review screen shows the result of this before the model mutator casts the
 * very same value again on the way in. Running twice must change nothing, or the
 * screen stops telling the truth about what will be recorded.
 */
it('changes nothing when run over its own output', function (string $exported): void {
    $once = AddressNormalizer::titleCase($exported);

    expect(AddressNormalizer::titleCase($once))->toBe($once);
})->with([
    'RUE DU TEST 13',
    'LOUVAIN-LA-NEUVE',
    "AVENUE DE L'EXEMPLE 4",
    'LA HULPE',
    'CHAUSSEE DE BRUXELLES 12A',
]);

it('returns null for nothing to normalise', function (): void {
    expect(AddressNormalizer::titleCase(null))->toBeNull()
        ->and(AddressNormalizer::titleCase(''))->toBeNull()
        ->and(AddressNormalizer::titleCase('   '))->toBeNull();
});
