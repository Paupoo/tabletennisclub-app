<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\Gender;
use App\Services\ClubAdmin\Users\FederationListingParser;

/*
 * The federation exports its affiliate listing as a Windows-1252, semicolon
 * separated, CRLF file. Every quirk exercised below was observed in a real
 * export; none of them is hypothetical.
 */

const FEDERATION_HEADER = 'Licence;Nom;DATE NAISSANCE;CH;CD;LFM;CONF;SA;Statut;Date 1ere affiliation;Email;Tel;GSM;Adresse;Numéro;CP;Localité';

/**
 * Build a listing the way the federation does: Windows-1252 bytes, CRLF endings.
 *
 * @param  array<int, string>  $lines
 */
function federationListing(array $lines): string
{
    $utf8 = implode("\r\n", [FEDERATION_HEADER, ...$lines]) . "\r\n";

    return mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
}

describe('parsing a federation listing', function (): void {

    it('turns a well formed line into a member ready to record', function (): void {
        $listing = federationListing([
            '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect($result->rows)->toHaveCount(1);

        $row = $result->rows[0];

        expect($row->licence)->toBe('166036')
            ->and($row->lastName)->toBe('Dupont')
            ->and($row->firstName)->toBe('Marc')
            ->and($row->birthdate?->toDateString())->toBe('1990-06-05')
            ->and($row->ranking)->toBe('C2')
            ->and($row->gender)->toBe(Gender::MEN)
            ->and($row->federationLicenceType)->toBe('JO')
            ->and($row->email)->toBe('marc@example.com')
            ->and($row->phone)->toBe('0475123456')
            ->and($row->street)->toBe('Rue du Test 13')
            ->and($row->cityCode)->toBe('1348')
            ->and($row->cityName)->toBe('Louvain-la-Neuve');

        expect($result->failures)->toBeEmpty();
    });

    /*
     * The listing is exported in capitals, and the review screen has to show what
     * will be recorded — not what the export happened to send.
     */
    it('reads an address out of the capitals the export writes it in', function (): void {
        $listing = federationListing([
            '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;CHAUSSEE DE BRUXELLES;12A;1348;OTTIGNIES-LOUVAIN-LA-NEUVE',
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect($result->rows[0]->street)->toBe('Chaussee de Bruxelles 12A')
            ->and($result->rows[0]->cityName)->toBe('Ottignies-Louvain-la-Neuve');
    });

    /*
     * An address identifies a login and is compared as a string throughout the
     * import. A member typed into the federation's system in capitals must not
     * become a second account beside the one the club already holds.
     */
    it('settles the casing of an email address', function (): void {
        $listing = federationListing([
            '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;Marc.DUPONT@Example.COM;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect($result->rows[0]->email)->toBe('marc.dupont@example.com');
    });

    it('restores accents the export encoded in Windows-1252', function (): void {
        $listing = federationListing([
            '176421;DUPUIS ZOé;2013-06-10;NC;NC;N;N;CA;LR;2025-08-08;zoe@example.com;;0489297619;PLACE DU CORTIL;6;1450;CHASTRE',
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect($result->rows[0]->firstName)->toBe('Zoé');
    });

    /*
     * The export escapes apostrophes several times over, so a street reaches us
     * as `AVENUE DE L\\\\'EXEMPLE`.
     */
    it('unescapes apostrophes the export escaped several times over', function (): void {
        $listing = federationListing([
            "166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;AVENUE DE L\\\\\\\\'EXEMPLE;13;1348;LOUVAIN-LA-NEUVE",
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect($result->rows[0]->street)->toBe("Avenue de l'Exemple 13");
    });

    it('flags a name it could not split with confidence', function (): void {
        $listing = federationListing([
            '166036;DE LA FONTAINE CLAIRE;1990-06-05;C2;NC;N;N;SE;JO;2020-09-24;claire@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            '166037;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123457;RUE DU TEST;14;1348;LOUVAIN-LA-NEUVE',
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect($result->rows[0]->lastName)->toBe('De La Fontaine')
            ->and($result->rows[0]->firstName)->toBe('Claire')
            ->and($result->rows[0]->gender)->toBe(Gender::WOMEN)
            ->and($result->rows[0]->needsNameReview)->toBeTrue();

        expect($result->rows[1]->needsNameReview)->toBeFalse();
    });

    /*
     * Some exports drop the house number, which shifts the postal code and the
     * town one column to the left. The member is still perfectly importable —
     * only their address needs a second look, so the row is kept and flagged.
     */
    it('keeps a row whose columns are shifted and asks for its address to be checked', function (): void {
        $listing = federationListing([
            '176703;MORIN CAMILLE;2008-09-19;NC;NC;N;N;21;LR;2025-09-04;camille@example.com;;0477200649;AVENUE DES IRIS;1341;CEROUX-MOUSTY;',
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect($result->rows)->toHaveCount(1)
            ->and($result->rows[0]->licence)->toBe('176703')
            ->and($result->rows[0]->needsAddressReview)->toBeTrue()
            ->and($result->failures)->toBeEmpty();
    });

    it('rejects a line that identifies nobody', function (): void {
        $listing = federationListing([
            ';DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            '166037;;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;other@example.com;;0475123457;RUE DU TEST;14;1348;LOUVAIN-LA-NEUVE',
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect($result->rows)->toBeEmpty()
            ->and($result->failures)->toHaveCount(2)
            ->and($result->failures[0]['line'])->toBe(2)
            ->and($result->failures[1]['line'])->toBe(3);
    });

    /*
     * A failure names a line and a reason. It must never carry the personal data
     * that line held: the import history outlives the file, which does not.
     */
    it('keeps personal data out of the rejection record', function (): void {
        $listing = federationListing([
            ';DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
        ]);

        $result = (new FederationListingParser)->parse($listing);

        expect(array_keys($result->failures[0]))->toBe(['line', 'reason']);

        $recorded = json_encode($result->failures[0]);

        expect($recorded)->not->toContain('DUPONT')
            ->and($recorded)->not->toContain('marc@example.com')
            ->and($recorded)->not->toContain('0475123456')
            ->and($recorded)->not->toContain('1990-06-05');
    });
});
