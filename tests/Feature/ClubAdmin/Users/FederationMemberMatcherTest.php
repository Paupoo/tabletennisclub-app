<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MemberMatchOutcome;
use App\Services\ClubAdmin\Users\FederationMemberMatcher;
use Carbon\CarbonImmutable;

describe('matching an affiliate against the club roster', function (): void {

    it('recognises a member by their licence number', function (): void {
        $existing = User::factory()->create([
            'licence' => '166036',
            'email' => 'something.else@example.com',
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->outcome)->toBe(MemberMatchOutcome::MATCHED)
            ->and($match->existing?->id)->toBe($existing->id);
    });

    it('recognises a member by their address, whatever the casing and spacing', function (): void {
        $existing = User::factory()->create([
            'licence' => null,
            'email' => 'marc@example.com',
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1990-06-05',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow([
            'email' => '  MARC@Example.COM ',
        ]));

        expect($match->outcome)->toBe(MemberMatchOutcome::MATCHED)
            ->and($match->existing?->id)->toBe($existing->id);
    });

    /*
     * The federation lists one mailbox per household: a child too young to have
     * one is carried under their parent's. An address therefore identifies a
     * family, not a person, and cannot be read as proof on its own.
     */
    it('does not take a child for the parent whose address was listed against them', function (): void {
        User::factory()->create([
            'licence' => '111111',
            'email' => 'famille.dupont@example.com',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'birthdate' => '1980-05-02',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow([
            'firstName' => 'Louis',
            'lastName' => 'Dupont',
            'birthdate' => CarbonImmutable::parse('2016-03-04'),
            'email' => 'famille.dupont@example.com',
        ]));

        expect($match->outcome)->toBe(MemberMatchOutcome::NEW)
            ->and($match->existing)->toBeNull();
    });

    // The guardian is not always the father: a mother keeping her maiden name,
    // an uncle, a legal guardian. The further the name is from the child's, the
    // plainer the rejection — it is a same-name hit that has to be feared.
    it('does not take a child for the mother whose address was listed against them', function (): void {
        User::factory()->create([
            'licence' => '111111',
            'email' => 'marie.lambert@example.com',
            'first_name' => 'Marie',
            'last_name' => 'Lambert',
            'birthdate' => '1982-09-14',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow([
            'firstName' => 'Louis',
            'lastName' => 'Dupont',
            'birthdate' => CarbonImmutable::parse('2016-03-04'),
            'email' => 'marie.lambert@example.com',
        ]));

        expect($match->outcome)->toBe(MemberMatchOutcome::NEW)
            ->and($match->existing)->toBeNull();
    });

    /*
     * The name alone would leave the household where a son carries his father's
     * exact name, and the club holds no birthdate for half its members. Each
     * guard covers what the other cannot see.
     */
    it('does not take a son for his father of the very same name', function (): void {
        User::factory()->create([
            'licence' => '111111',
            'email' => 'marc@example.com',
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1962-11-30',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->outcome)->not->toBe(MemberMatchOutcome::MATCHED);
    });

    it('recognises a member by their name and birthdate when nothing else identifies them', function (): void {
        $existing = User::factory()->create([
            'licence' => null,
            'email' => 'typed.in.by.hand@example.com',
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1990-06-05',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->outcome)->toBe(MemberMatchOutcome::MATCHED)
            ->and($match->existing?->id)->toBe($existing->id);
    });

    it('ignores accents and casing when comparing names', function (): void {
        $existing = User::factory()->create([
            'licence' => null,
            'email' => 'other@example.com',
            'first_name' => 'Zoé',
            'last_name' => 'De La Fontaine',
            'birthdate' => '1990-06-05',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow([
            'firstName' => 'ZOE',
            'lastName' => 'de la fontaine',
        ]));

        expect($match->existing?->id)->toBe($existing->id);
    });

    /*
     * The guard that stops the cascade from turning a missing birthdate into a
     * wildcard: seven members were recorded without one, and matching on name
     * alone would hand each of them the first namesake the federation sends.
     */
    it('refuses to match on a name when the member on file has no birthdate', function (): void {
        User::factory()->create([
            'licence' => null,
            'email' => 'other@example.com',
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => null,
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->outcome)->toBe(MemberMatchOutcome::SUSPECT)
            ->and($match->existing)->not->toBeNull();
    });

    it('treats a namesake born on another day as a question, not a match', function (): void {
        User::factory()->create([
            'licence' => null,
            'email' => 'other@example.com',
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1975-01-01',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->outcome)->toBe(MemberMatchOutcome::SUSPECT);
    });

    it('reports an affiliate the club has never heard of as new', function (): void {
        User::factory()->create(['licence' => '999999', 'email' => 'someone@example.com']);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->outcome)->toBe(MemberMatchOutcome::NEW)
            ->and($match->existing)->toBeNull();
    });

    /*
     * A member who left and comes back on the federation listing must be offered
     * for restoration, never duplicated: their history, payments and past
     * seasons hang off the archived row.
     */
    it('surfaces an archived member rather than creating a second one', function (): void {
        $archived = User::factory()->create(['licence' => '166036']);
        $archived->delete();

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->outcome)->toBe(MemberMatchOutcome::ARCHIVED)
            ->and($match->existing?->id)->toBe($archived->id);
    });
});

/*
 * Discrepancies are what the federation says and the club does not, on fields the
 * club owns. They are reported and never applied — each of them can just as well
 * mean the match itself is wrong.
 */
describe('reporting what the federation disagrees with', function (): void {

    it('reports an address the club never updated', function (): void {
        User::factory()->create([
            'licence' => '166036',
            'street' => 'ANCIENNE ADRESSE 1',
            'city_code' => '1300',
            'city_name' => 'WAVRE',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->discrepancies)->toContain('address');
    });

    // Seeded in its stored casing, so the assertion repeats the seed word for
    // word: any difference here is the matcher writing, not AddressNormalizer
    // casting the value on the way in.
    it('reports an address the federation holds differently, without touching it', function (): void {
        $existing = User::factory()->create([
            'licence' => '166036',
            'street' => 'Ancienne Adresse 1',
        ]);

        (new FederationMemberMatcher)->match(federationRow());

        expect($existing->fresh()->street)->toBe('Ancienne Adresse 1');
    });

    it('reports a birthdate that contradicts the one on file', function (): void {
        User::factory()->create([
            'licence' => '166036',
            'birthdate' => '1975-01-01',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->discrepancies)->toContain('birthdate');
    });

    /*
     * Matched by address but under another licence: either the club mistyped one,
     * or these are two people in the same household. Both are settled by hand.
     */
    it('reports a licence that contradicts the one on file', function (): void {
        User::factory()->create([
            'licence' => '111111',
            'email' => 'marc@example.com',
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1990-06-05',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->discrepancies)->toContain('licence');
    });

    it('says nothing when the club and the federation agree', function (): void {
        User::factory()->create([
            'licence' => '166036',
            'email' => 'marc@example.com',
            'birthdate' => '1990-06-05',
            'street' => 'RUE DU TEST 13',
            'city_code' => '1348',
            'city_name' => 'LOUVAIN-LA-NEUVE',
        ]);

        $match = (new FederationMemberMatcher)->match(federationRow());

        expect($match->discrepancies)->toBeEmpty();
    });
});
