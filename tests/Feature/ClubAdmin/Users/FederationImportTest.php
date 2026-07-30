<?php

declare(strict_types=1);

use App\Actions\User\ImportFederationMembersAction;
use App\Data\User\ImportLine;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\MemberImport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ImportLineAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/*
 * The import records people. It never writes to them — an affiliate discovers the
 * club's application when the committee decides they should, not when a file is
 * dropped on a form in the middle of August.
 */

describe('importing the federation listing', function (): void {

    it('records a member the club did not know', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        $import = ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(), action: ImportLineAction::CREATE),
        ], $secretary);

        $created = User::query()->where('licence', '166036')->first();

        expect($created)->not->toBeNull()
            ->and($created->first_name)->toBe('Marc')
            ->and($created->last_name)->toBe('Dupont')
            ->and($created->email)->toBe('marc@example.com')
            ->and($created->ranking)->toBe('C2')
            ->and($created->federation_licence_type)->toBe('JO')
            ->and($created->member_import_id)->toBe($import->id);

        expect($import->new_count)->toBe(1);
    });

    /*
     * The guarantee the whole feature is built around. It does not rest on a flag
     * that could be forgotten: the import simply does not go through
     * CreateUserAction, which invites whenever no password is set.
     */
    it('sends absolutely nothing', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['lineNumber' => 2, 'licence' => '166036', 'email' => 'a@example.com']), action: ImportLineAction::CREATE),
            new ImportLine(row: federationRow(['lineNumber' => 3, 'licence' => '166037', 'email' => 'b@example.com']), action: ImportLineAction::CREATE),
        ], $secretary);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    });

    it('leaves an imported member uninvited and unregistered', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(), action: ImportLineAction::CREATE),
        ], $secretary);

        $created = User::query()->where('licence', '166036')->first();

        expect($created->last_invited_at)->toBeNull()
            ->and($created->email_verified_at)->toBeNull()
            ->and($created->invitationStatus())->toBe('not_invited');
    });

    it('records when the federation data was read', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(), action: ImportLineAction::CREATE),
        ], $secretary);

        expect(User::query()->where('licence', '166036')->first()->federation_synced_at)->not->toBeNull();
    });

    it('keeps a run of the import for the record', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        $import = ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['licence' => '166036']), action: ImportLineAction::CREATE),
            new ImportLine(row: federationRow(['licence' => '166037']), action: ImportLineAction::SKIP),
        ], $secretary, failures: [['line' => 9, 'reason' => 'Missing licence number.']]);

        expect($import)->toBeInstanceOf(MemberImport::class)
            ->and($import->user_id)->toBe($secretary->id)
            ->and($import->new_count)->toBe(1)
            ->and($import->skipped_count)->toBe(1)
            ->and($import->error_count)->toBe(1)
            ->and($import->failed_rows)->toBe([['line' => 9, 'reason' => 'Missing licence number.']]);
    });

    it('records a minor phone number as the one to reach their guardian on', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['birthdate' => CarbonImmutable::parse('2014-04-25'), 'phone' => '0475111222']),
                action: ImportLineAction::CREATE,
            ),
        ], $secretary);

        $child = User::query()->where('licence', '166036')->first();

        expect($child->phone_number)->toBeNull()
            ->and($child->guardian_phone_number)->toBe('0475111222');
    });

    it('does not record a line the reviewer chose to skip', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(), action: ImportLineAction::SKIP),
        ], $secretary);

        expect(User::query()->where('licence', '166036')->exists())->toBeFalse();
    });
});

/*
 * Several affiliates share one address, so only one of them may keep it as a
 * login. The others are recorded without one and reached through a guardian —
 * which is the whole reason `users.email` was made nullable.
 */
describe('importing a family under one address', function (): void {

    it('records the children without an address of their own', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['lineNumber' => 2, 'licence' => '166036', 'firstName' => 'Cristina', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('1984-03-19')]),
                action: ImportLineAction::CREATE,
            ),
            new ImportLine(
                row: federationRow(['lineNumber' => 3, 'licence' => '166037', 'firstName' => 'Luke', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('2017-09-19')]),
                action: ImportLineAction::CREATE,
                keepsEmail: false,
                guardianLineNumber: 2,
            ),
        ], $secretary);

        expect(User::query()->where('licence', '166036')->first()->email)->toBe('parent@example.com')
            ->and(User::query()->where('licence', '166037')->first()->email)->toBeNull();
    });

    it('makes the affiliated adult the guardian of the child', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['lineNumber' => 2, 'licence' => '166036', 'firstName' => 'Cristina', 'lastName' => 'Dupuis', 'email' => 'parent@example.com', 'phone' => '0489297619', 'birthdate' => CarbonImmutable::parse('1984-03-19')]),
                action: ImportLineAction::CREATE,
            ),
            new ImportLine(
                row: federationRow(['lineNumber' => 3, 'licence' => '166037', 'firstName' => 'Luke', 'lastName' => 'Dupuis', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('2017-09-19')]),
                action: ImportLineAction::CREATE,
                keepsEmail: false,
                guardianLineNumber: 2,
            ),
        ], $secretary);

        $parent = User::query()->where('licence', '166036')->first();
        $child = User::query()->where('licence', '166037')->first();
        $guardian = $child->guardians()->first();

        expect($guardian)->not->toBeNull()
            ->and($guardian->user_id)->toBe($parent->id)
            ->and($guardian->first_name)->toBe('Cristina')
            ->and($guardian->last_name)->toBe('Dupuis')
            ->and($guardian->email)->toBe('parent@example.com');
    });

    it('reaches the child through their guardian', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['lineNumber' => 2, 'licence' => '166036', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('1984-03-19')]),
                action: ImportLineAction::CREATE,
            ),
            new ImportLine(
                row: federationRow(['lineNumber' => 3, 'licence' => '166037', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('2017-09-19')]),
                action: ImportLineAction::CREATE,
                keepsEmail: false,
                guardianLineNumber: 2,
            ),
        ], $secretary);

        $child = User::query()->where('licence', '166037')->first();

        expect($child->contactEmail())->toBe('parent@example.com');
    });

    it('gives two children of the same parent one guardian, not two', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['lineNumber' => 2, 'licence' => '166036', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('1984-03-19')]), action: ImportLineAction::CREATE),
            new ImportLine(row: federationRow(['lineNumber' => 3, 'licence' => '166037', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('2017-09-19')]), action: ImportLineAction::CREATE, keepsEmail: false, guardianLineNumber: 2),
            new ImportLine(row: federationRow(['lineNumber' => 4, 'licence' => '166038', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('2013-06-10')]), action: ImportLineAction::CREATE, keepsEmail: false, guardianLineNumber: 2),
        ], $secretary);

        expect(Guardian::count())->toBe(1);

        $parent = User::query()->where('licence', '166036')->first();

        expect(Guardian::first()->users()->pluck('users.id')->sort()->values()->all())
            ->toBe(User::query()->whereIn('licence', ['166037', '166038'])->pluck('id')->sort()->values()->all())
            ->and(Guardian::first()->user_id)->toBe($parent->id);
    });

    /*
     * Nobody on the address plays: the parent exists but is not affiliated. The
     * club records them as a guardian without a member account of their own.
     */
    it('records an outside guardian when no adult on the address is affiliated', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['lineNumber' => 2, 'licence' => '166037', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('2014-04-25')]),
                action: ImportLineAction::CREATE,
                keepsEmail: false,
                externalGuardian: true,
                guardianFirstName: 'Olivier',
                guardianLastName: 'Gilbert',
                guardianEmail: 'parent@example.com',
                guardianPhone: '0475111222',
            ),
        ], $secretary);

        $child = User::query()->where('licence', '166037')->first();
        $guardian = $child->guardians()->first();

        expect($child->email)->toBeNull()
            ->and($guardian->user_id)->toBeNull()
            ->and($guardian->first_name)->toBe('Olivier')
            ->and($guardian->last_name)->toBe('Gilbert')
            ->and($guardian->email)->toBe('parent@example.com')
            ->and($guardian->phone)->toBe('0475111222')
            ->and($child->contactEmail())->toBe('parent@example.com');
    });

    /*
     * The reviewer ticked "this address belongs to a guardian" without naming
     * them. Inventing an identity is out of the question, so the address is still
     * withheld — the member simply shows up as having none, which is visible and
     * fixable, where a made-up guardian would not be.
     */
    it('withholds the address rather than inventing a guardian nobody named', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['lineNumber' => 2, 'licence' => '166037', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('2014-04-25')]),
                action: ImportLineAction::CREATE,
                keepsEmail: false,
                externalGuardian: true,
                guardianEmail: 'parent@example.com',
            ),
        ], $secretary);

        expect(User::query()->where('licence', '166037')->first()->email)->toBeNull()
            ->and(Guardian::count())->toBe(0);
    });
});

/*
 * The rule for a member the club already holds: the federation overwrites what it
 * owns — the licence number it issues, the address it affiliates people on — and
 * the club keeps everything a human may have touched. A listing is a year old the
 * day after it is exported, so it must not be allowed to undo a correction
 * somebody made to a name, a login or a phone number in the meantime.
 */
describe('re-importing a member the club already holds', function (): void {

    it('takes the new ranking from the federation', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '166036', 'ranking' => 'D4']);

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['ranking' => 'C2']), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($member->fresh()->ranking)->toBe('C2');
    });

    /*
     * A member the club archived turns up in the federation's listing again: they
     * came back. Nobody chose that update lightly — the screen never proposes it,
     * a human went looking for the archived file and pointed at it — and leaving
     * them archived would mean going to find them a second time, by hand.
     */
    it('brings back a member who was archived when the reviewer points at their file', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '166036', 'ranking' => 'D4']);
        $member->delete();

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['ranking' => 'C2']), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        $restored = User::query()->find($member->id);

        expect($restored)->not->toBeNull()
            ->and($restored->trashed())->toBeFalse()
            ->and($restored->ranking)->toBe('C2');
    });

    it('takes the new licence type from the federation', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '166036', 'federation_licence_type' => 'LR']);

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['federationLicenceType' => 'JO']), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($member->fresh()->federation_licence_type)->toBe('JO');
    });

    /*
     * The licence number is issued by the federation. A club file carrying another
     * one is a club-side mistake, whatever it was typed from, and the reviewer saw
     * the difference reported before choosing to update.
     */
    it('takes the licence number from the federation over the one on file', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '111111']);

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['licence' => '166036']), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($member->fresh()->licence)->toBe('166036');
    });

    it('takes the postal address from the federation over the one on file', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create([
            'licence' => '166036',
            'street' => 'Rue Ancienne 1',
            'city_code' => '1000',
            'city_name' => 'Bruxelles',
        ]);

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        $updated = $member->fresh();

        // Stored in the casing a human writes, not the capitals the export uses:
        // the address crosses AddressNormalizer on its way in. See {@see User::setStreetAttribute()}.
        expect($updated->street)->toBe('Rue du Test 13')
            ->and($updated->city_code)->toBe('1348')
            ->and($updated->city_name)->toBe('Louvain-la-Neuve');
    });

    /*
     * A column the export left empty says the file has nothing on it, not that the
     * club should forget what it knows.
     */
    it('keeps what the club holds where the listing says nothing', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create([
            'licence' => '166036',
            'street' => 'Rue Ancienne 1',
            'city_code' => '1000',
            'city_name' => 'Bruxelles',
        ]);

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['licence' => '', 'street' => null, 'cityCode' => null, 'cityName' => null]),
                action: ImportLineAction::UPDATE,
                existingUserId: $member->id,
            ),
        ], $secretary);

        $updated = $member->fresh();

        expect($updated->licence)->toBe('166036')
            ->and($updated->street)->toBe('Rue Ancienne 1')
            ->and($updated->city_code)->toBe('1000')
            ->and($updated->city_name)->toBe('Bruxelles');
    });

    /*
     * The email address is a login, not a fact the federation owns. The listing
     * keeps showing a parent's against a child for years after the club gave that
     * child one of their own, and overwriting would merge back the two accounts
     * that were just separated.
     */
    it('never overwrites an email address the club already holds', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '166036', 'email' => 'own.address@example.com']);

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['email' => 'parent@example.com']), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($member->fresh()->email)->toBe('own.address@example.com');
    });

    it('fills an email address the club never had', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '166036', 'email' => null]);

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['email' => 'from.federation@example.com']), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($member->fresh()->email)->toBe('from.federation@example.com');
    });

    /*
     * An update is not a way around the one-address-one-login rule: a child on
     * the parent's mailbox is reached through a guardian whether the club is
     * meeting them for the first time or has held their file for years.
     */
    it('leaves an updated member without a login when the address is not theirs to keep', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $child = User::factory()->create(['licence' => '166037', 'email' => null]);

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['lineNumber' => 2, 'licence' => '166036', 'firstName' => 'Cristina', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('1984-03-19')]),
                action: ImportLineAction::CREATE,
            ),
            new ImportLine(
                row: federationRow(['lineNumber' => 3, 'licence' => '166037', 'firstName' => 'Luke', 'email' => 'parent@example.com', 'birthdate' => CarbonImmutable::parse('2017-09-19')]),
                action: ImportLineAction::UPDATE,
                existingUserId: $child->id,
                keepsEmail: false,
                guardianLineNumber: 2,
            ),
        ], $secretary);

        expect($child->fresh()->email)->toBeNull()
            ->and(User::query()->where('licence', '166036')->first()->email)->toBe('parent@example.com');
    });

    /*
     * The parent kept the family mailbox years ago and is not in this listing —
     * nothing in the file says so. Writing it onto the child would break the
     * unique index and take the whole import down with it.
     */
    it('never hands a member an address another member already holds', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        User::factory()->create(['email' => 'parent@example.com']);
        $child = User::factory()->create(['licence' => '166037', 'email' => null]);

        $import = ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['licence' => '166037', 'email' => 'parent@example.com']),
                action: ImportLineAction::UPDATE,
                existingUserId: $child->id,
            ),
        ], $secretary);

        expect($child->fresh()->email)->toBeNull()
            ->and($import->updated_count)->toBe(1);
    });

    /*
     * Unreachable through the review screen, which matches on the licence before
     * anything else — but a listing carrying the same number twice must not take
     * the transaction down with it either.
     */
    it('never hands a member a licence number another member already holds', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        User::factory()->create(['licence' => '166036']);
        $member = User::factory()->create(['licence' => '111111']);

        $import = ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['licence' => '166036']), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($member->fresh()->licence)->toBe('111111')
            ->and($import->updated_count)->toBe(1);
    });

    /*
     * The reviewer corrected `VANDENBERGHE ANNE SOPHIE` by hand. Next year's file
     * holds the same raw string, and an update would undo the correction every
     * season.
     */
    it('never rewrites a name a human settled', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create([
            'licence' => '166036',
            'first_name' => 'Anne Sophie',
            'last_name' => 'Vandenberghe',
        ]);

        ImportFederationMembersAction::handle([
            new ImportLine(
                row: federationRow(['firstName' => 'Sophie', 'lastName' => 'Vandenberghe Anne']),
                action: ImportLineAction::UPDATE,
                existingUserId: $member->id,
            ),
        ], $secretary);

        expect($member->fresh()->first_name)->toBe('Anne Sophie')
            ->and($member->fresh()->last_name)->toBe('Vandenberghe');
    });

    it('never overwrites a phone number the club already holds', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '166036', 'phone_number' => '0470000000']);

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(['phone' => '0475123456']), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($member->fresh()->phone_number)->toBe('0470000000');
    });

    it('counts the member as updated, not as new', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '166036']);

        $import = ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($import->new_count)->toBe(0)
            ->and($import->updated_count)->toBe(1)
            ->and(User::query()->where('licence', '166036')->count())->toBe(1);
    });

    /*
     * Provenance belongs to the members this run brought in. A member who already
     * existed was not imported, however much of their data the run refreshed.
     */
    it('does not claim a member it merely refreshed', function (): void {
        Mail::fake();
        $secretary = User::factory()->create();
        $member = User::factory()->create(['licence' => '166036']);

        ImportFederationMembersAction::handle([
            new ImportLine(row: federationRow(), action: ImportLineAction::UPDATE, existingUserId: $member->id),
        ], $secretary);

        expect($member->fresh()->member_import_id)->toBeNull();
    });
});
