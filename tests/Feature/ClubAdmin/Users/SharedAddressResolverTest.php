<?php

declare(strict_types=1);

use App\Services\ClubAdmin\Users\SharedAddressResolver;
use Carbon\CarbonImmutable;

/*
 * Several affiliates routinely share one address: a parent enrols their children,
 * and the federation records the parent's mailbox against each of them. An email
 * identifies a login, so exactly one of them may keep it — the others are reached
 * through a guardian.
 *
 * `federationRow()` is defined in FederationMemberMatcherTest.php.
 */

function born(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

describe('resolving a shared address', function (): void {

    it('leaves untouched every affiliate with an address of their own', function (): void {
        $decisions = (new SharedAddressResolver)->resolve([
            federationRow(['lineNumber' => 2, 'email' => 'one@example.com']),
            federationRow(['lineNumber' => 3, 'email' => 'two@example.com']),
        ]);

        expect($decisions)->toBeEmpty();
    });

    it('lets the adult keep the address and reaches the children through them', function (): void {
        $decisions = (new SharedAddressResolver)->resolve([
            federationRow(['lineNumber' => 2, 'email' => 'parent@example.com', 'birthdate' => born('2017-09-19')]),
            federationRow(['lineNumber' => 3, 'email' => 'parent@example.com', 'birthdate' => born('1984-03-19')]),
            federationRow(['lineNumber' => 4, 'email' => 'parent@example.com', 'birthdate' => born('2013-06-10')]),
        ]);

        expect($decisions[3]->keepsEmail)->toBeTrue()
            ->and($decisions[3]->guardianLineNumber)->toBeNull();

        expect($decisions[2]->keepsEmail)->toBeFalse()
            ->and($decisions[2]->guardianLineNumber)->toBe(3);

        expect($decisions[4]->keepsEmail)->toBeFalse()
            ->and($decisions[4]->guardianLineNumber)->toBe(3);
    });

    /*
     * Nobody on the address is affiliated as an adult: the parent exists but does
     * not play. The address then belongs to a guardian the club records without a
     * member account of their own.
     */
    it('records an outside guardian when no adult on the address is affiliated', function (): void {
        $decisions = (new SharedAddressResolver)->resolve([
            federationRow(['lineNumber' => 2, 'email' => 'parent@example.com', 'birthdate' => born('2014-04-25'), 'phone' => '0475111222']),
            federationRow(['lineNumber' => 3, 'email' => 'parent@example.com', 'birthdate' => born('2012-06-06')]),
        ]);

        expect($decisions[2]->keepsEmail)->toBeFalse()
            ->and($decisions[2]->externalGuardian)->toBeTrue()
            ->and($decisions[2]->guardianEmail)->toBe('parent@example.com')
            ->and($decisions[2]->guardianPhone)->toBe('0475111222');

        expect($decisions[3]->keepsEmail)->toBeFalse()
            ->and($decisions[3]->externalGuardian)->toBeTrue();
    });

    /*
     * Two adults on one address are a couple, not a guardianship. One keeps the
     * login; the other is not handed a guardian — they are asked for an address
     * of their own, which is a question for the secretary and not for the parser.
     */
    it('never makes one adult the guardian of another', function (): void {
        $decisions = (new SharedAddressResolver)->resolve([
            federationRow(['lineNumber' => 2, 'email' => 'couple@example.com', 'birthdate' => born('1980-01-01')]),
            federationRow(['lineNumber' => 3, 'email' => 'couple@example.com', 'birthdate' => born('1982-01-01')]),
        ]);

        expect($decisions[2]->keepsEmail)->toBeTrue();

        expect($decisions[3]->keepsEmail)->toBeFalse()
            ->and($decisions[3]->guardianLineNumber)->toBeNull()
            ->and($decisions[3]->externalGuardian)->toBeFalse();
    });

    it('ignores casing and spacing when deciding two affiliates share an address', function (): void {
        $decisions = (new SharedAddressResolver)->resolve([
            federationRow(['lineNumber' => 2, 'email' => 'Parent@Example.com', 'birthdate' => born('1984-03-19')]),
            federationRow(['lineNumber' => 3, 'email' => ' parent@example.COM ', 'birthdate' => born('2013-06-10')]),
        ]);

        expect($decisions[3]->guardianLineNumber)->toBe(2);
    });

    it('says nothing about affiliates the federation listed without any address', function (): void {
        $decisions = (new SharedAddressResolver)->resolve([
            federationRow(['lineNumber' => 2, 'email' => null]),
            federationRow(['lineNumber' => 3, 'email' => null]),
        ]);

        expect($decisions)->toBeEmpty();
    });
});
