<?php

declare(strict_types=1);

use App\Support\Treasury\BankStatementReader;

/**
 * Les deux exports que CBC produit réellement, anonymisés mais intacts octet
 * pour octet : encodage, fins de ligne, séparateur, remplissage.
 */
function bankSample(string $name): string
{
    return base_path('tests/Fixtures/BankStatements/' . $name);
}

it('reads the quick export, whose lines end with a lone carriage return', function (): void {
    $rows = (new BankStatementReader)->read(bankSample('cbc-quick-export.csv'))->rows;

    expect($rows)->toHaveCount(4)
        ->and($rows[1])->toMatchArray([
            'line' => 3,
            'account' => 'BE68539007547034',
            'date' => '28/09/2026',
            'amount' => '1234,50',
            'counterparty_account' => 'BE47 6511 5466 4280',
            'counterparty_name' => 'CHLOÉ LUYTEN',
            'structured_reference' => null,
            'free_reference' => 'Cotisation',
        ])
        ->and($rows[2]['structured_reference'])->toBe('***026/0000/89457***');
});

it('reads the period export, separated by commas and naming its columns differently', function (): void {
    $rows = (new BankStatementReader)->read(bankSample('cbc-period-export.csv'))->rows;

    expect($rows)->toHaveCount(3)
        ->and($rows[2])->toMatchArray([
            'line' => 4,
            'account' => 'BE68 5390 0754 7034',
            'date' => '16/07/2026',
            'amount' => '-1296,00',
            'counterparty_account' => 'BE98 0010 6227 5793',
            'counterparty_name' => 'COMPLEXE SPORTIF EXEMPLE',
            'structured_reference' => '***026/0000/89457***',
            'free_reference' => null,
        ]);
});

it('refuses a statement that lacks a column it cannot do without, and says which', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bank');
    file_put_contents($path, "Date;Description;Communication libre\n28/09/2026;VIREMENT;Cotisation\n");

    expect(fn (): mixed => (new BankStatementReader)->read($path))
        ->toThrow(DomainException::class, 'Montant');
});

it('refuses a statement that carries neither communication column', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bank');
    file_put_contents($path, "Date;Montant;Numéro de compte contrepartie\n28/09/2026;40,00;BE47\n");

    expect(fn (): mixed => (new BankStatementReader)->read($path))
        ->toThrow(DomainException::class, 'Communication');
});

it('names the columns it did find when it refuses a statement', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bank');
    file_put_contents($path, "Datum;Bedrag;Mededeling\n28/09/2026;40,00;Lidgeld\n");

    expect(fn (): mixed => (new BankStatementReader)->read($path))
        ->toThrow(DomainException::class, 'Datum, Bedrag, Mededeling');
});

/**
 * Un échantillon normalisé par git ou par un éditeur continuerait de passer
 * les tests ci-dessus sans plus rien prouver : c'est son octet qu'on teste.
 */
it('keeps the samples byte for byte as the bank wrote them', function (): void {
    $quick = (string) file_get_contents(bankSample('cbc-quick-export.csv'));
    $period = (string) file_get_contents(bankSample('cbc-period-export.csv'));

    expect($quick)->toContain("\r")->not->toContain("\n")
        ->and(mb_check_encoding($quick, 'UTF-8'))->toBeFalse()
        ->and($period)->toContain("\r\n")
        ->and(mb_check_encoding($period, 'UTF-8'))->toBeTrue();
});
