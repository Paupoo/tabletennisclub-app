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

/*
 * The same rule is asked twice: of the export, when the file is read, and of
 * whatever the reviewer typed in its place. A correction is only a correction if
 * it satisfies the rule that rejected the original.
 */
describe('spotting an address the export shifted', function (): void {

    it('accepts a complete Belgian address', function (): void {
        expect(AddressNormalizer::looksShifted('Rue du Test 13', '1348', 'Louvain-la-Neuve'))->toBeFalse();
    });

    it('spots a locality standing where the postcode belongs', function (): void {
        expect(AddressNormalizer::looksShifted('Rue du Test 13', 'Louvain-la-Neuve', null))->toBeTrue();
    });

    it('spots a postcode that is not four digits', function (): void {
        expect(AddressNormalizer::looksShifted('Rue du Test 13', '134', 'Louvain-la-Neuve'))->toBeTrue()
            ->and(AddressNormalizer::looksShifted('Rue du Test 13', '13480', 'Louvain-la-Neuve'))->toBeTrue();
    });

    it('spots a street left without a locality', function (): void {
        expect(AddressNormalizer::looksShifted('Rue du Test 13', '1348', null))->toBeTrue()
            ->and(AddressNormalizer::looksShifted('Rue du Test 13', '1348', '  '))->toBeTrue();
    });

    /*
     * No street, no address: an affiliate the export carries nothing for is not
     * an affiliate whose address was shifted, and flagging them would send every
     * such line to a reviewer with nothing to review.
     */
    it('says nothing about an address the export never carried', function (): void {
        expect(AddressNormalizer::looksShifted(null, null, null))->toBeFalse()
            ->and(AddressNormalizer::looksShifted('', 'Louvain-la-Neuve', null))->toBeFalse();
    });
});
