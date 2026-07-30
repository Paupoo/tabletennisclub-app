<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\FederationRow;
use App\Data\User\ImportLine;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\MemberImport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ImportLineAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Writes the reviewed federation listing into the club roster.
 *
 * Deliberately not built on {@see CreateUserAction}: that one sends an invitation
 * whenever no password is set, which is exactly what must not happen here. An
 * affiliate discovers the application when the committee decides they should, not
 * because a file was dropped on a form in the middle of August. The guarantee
 * rests on this action simply having no code that sends anything — not on a flag
 * that a later change could forget to pass.
 */
class ImportFederationMembersAction
{
    /**
     * @param  array<int, ImportLine>  $lines
     * @param  array<int, array{line: int, reason: string}>  $failures  Lines the parser could not read.
     */
    public static function handle(array $lines, User $actor, array $failures = []): MemberImport
    {
        return DB::transaction(function () use ($lines, $actor, $failures): MemberImport {
            $import = MemberImport::create([
                'user_id' => $actor->id,
                'error_count' => count($failures),
                'failed_rows' => $failures === [] ? null : $failures,
            ]);

            $new = 0;
            $updated = 0;
            $skipped = 0;

            /** @var array<int, User> $byLine Members written by this run, by listing line. */
            $byLine = [];

            // Without events: UserObserver rebuilds the force lists on every
            // ranking, gender or birthdate change, and a listing of sixty members
            // would rebuild them sixty times. They are rebuilt once, at the end.
            User::withoutEvents(function () use ($lines, $import, &$new, &$updated, &$skipped, &$byLine): void {
                foreach ($lines as $line) {
                    if ($line->action === ImportLineAction::SKIP) {
                        $skipped++;

                        continue;
                    }

                    if ($line->action === ImportLineAction::CREATE) {
                        $byLine[$line->row->lineNumber] = self::create($line, $import);
                        $new++;

                        continue;
                    }

                    $member = self::update($line);

                    if ($member !== null) {
                        $byLine[$line->row->lineNumber] = $member;
                        $updated++;
                    }
                }
            });

            // Guardians are wired once every member exists: a child's guardian is
            // named by the line of an adult who may be created after them.
            foreach ($lines as $line) {
                self::linkGuardian($line, $byLine);
            }

            $import->update([
                'new_count' => $new,
                'updated_count' => $updated,
                'skipped_count' => $skipped,
            ]);

            RecalculateForceListAction::handle();

            return $import->refresh();
        });
    }

    private static function create(ImportLine $line, MemberImport $import): User
    {
        $row = $line->row;
        $minor = self::isMinor($row);

        return User::create([
            'first_name' => $row->firstName,
            'last_name' => $row->lastName,
            'email' => self::loginAddress($line),
            'gender' => $row->gender,
            'birthdate' => $row->birthdate,
            // The federation lists one number per affiliate, and for a child it is
            // whoever answers for them. Recording it as the member's own would have
            // the club ring a ten-year-old about an unpaid affiliation.
            'phone_number' => $minor ? null : $row->phone,
            'guardian_phone_number' => $minor ? $row->phone : null,
            'street' => $row->street,
            'city_code' => $row->cityCode,
            'city_name' => $row->cityName,
            'licence' => $row->licence,
            'ranking' => $row->ranking,
            'federation_licence_type' => $row->federationLicenceType,
            'federation_synced_at' => now(),
            'member_import_id' => $import->id,
            // No password and no invitation: the account exists, nobody has been
            // told about it, and `last_invited_at` staying null is what the
            // members list reads to offer the invitation later.
            'password' => null,
        ]);
    }

    private static function fillIfMissing(User $member, string $attribute, mixed $value): void
    {
        if ($value !== null && $member->getAttribute($attribute) === null) {
            $member->setAttribute($attribute, $value);
        }
    }

    private static function isMinor(FederationRow $row): bool
    {
        return $row->birthdate !== null && $row->birthdate->age < 18;
    }

    /**
     * Give a member who has no address of their own somebody to be reached at.
     *
     * Two shapes: the guardian is an affiliated adult listed on the same address,
     * or they do not play at all and the club records them without a member
     * account. In both cases the guardian is reused rather than duplicated — a
     * parent with three children is one guardian, not three.
     *
     * @param  array<int, User>  $byLine
     */
    private static function linkGuardian(ImportLine $line, array $byLine): void
    {
        $member = $byLine[$line->row->lineNumber] ?? null;

        if ($member === null) {
            return;
        }

        $guardian = $line->externalGuardian
            ? self::outsideGuardian($line)
            : self::memberGuardian($line, $byLine);

        if ($guardian === null) {
            return;
        }

        $member->guardians()->syncWithoutDetaching([$guardian->id]);
    }

    /**
     * The address this affiliate may keep as a login, if any.
     *
     * Two things take it away. The review screen settles which of several
     * affiliates listed under one address keeps it, the others being reached
     * through a guardian. And then the roster has the last word: the listing
     * carries a parent's mailbox against every child of the household, and that
     * parent may be on file already — under this listing or from years before
     * it. An address identifies one login and one only.
     *
     * Losing it costs the member nothing they had: they held no address of their
     * own, and the club can hand them a login the day they have one.
     */
    private static function loginAddress(ImportLine $line, ?User $member = null): ?string
    {
        return self::unlessTaken('email', $line->keepsEmail ? $line->row->email : null, $member);
    }

    /**
     * The guardian is an affiliated adult sharing the address: their identity is
     * already known, so it is taken from their own record.
     *
     * @param  array<int, User>  $byLine
     */
    private static function memberGuardian(ImportLine $line, array $byLine): ?Guardian
    {
        $adult = $line->guardianLineNumber === null ? null : ($byLine[$line->guardianLineNumber] ?? null);

        if ($adult === null) {
            return null;
        }

        return Guardian::firstOrCreate(
            ['user_id' => $adult->id],
            [
                'first_name' => $adult->first_name,
                'last_name' => $adult->last_name,
                'phone' => $adult->phone_number ?? $adult->guardian_phone_number ?? '',
                'email' => $adult->email,
            ],
        );
    }

    /**
     * The guardian does not play, so the listing never names them — the reviewer
     * does, on the import screen.
     *
     * Without a name there is no guardian: inventing an identity to hold an
     * address is out of the question, and the member simply shows up as having
     * none, which is visible and fixable where a made-up person would not be.
     */
    private static function outsideGuardian(ImportLine $line): ?Guardian
    {
        if ($line->guardianFirstName === null || $line->guardianLastName === null) {
            return null;
        }

        return Guardian::firstOrCreate(
            [
                'user_id' => null,
                'email' => $line->guardianEmail,
            ],
            [
                'first_name' => $line->guardianFirstName,
                'last_name' => $line->guardianLastName,
                'phone' => $line->guardianPhone ?? '',
            ],
        );
    }

    /**
     * Take the federation's value over whatever the club holds.
     *
     * An empty cell is never written back: the file having nothing to say about
     * a field is not the federation saying the club should forget it.
     */
    private static function overwrite(User $member, string $attribute, mixed $value): void
    {
        if ($value !== null) {
            $member->setAttribute($attribute, $value);
        }
    }

    /**
     * The value, unless some other member already holds it on a column the
     * database keeps unique.
     *
     * A whole import runs in one transaction, so a single collision would throw
     * away every line that came before it. What the club holds stays where it
     * is, the import goes through, and the difference the matcher reported is
     * there to be read afterwards. Archived members count: their file holds the
     * value just as much, which is exactly how a returning member is found.
     */
    private static function unlessTaken(string $column, ?string $value, ?User $member = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $taken = User::withTrashed()
            ->where($column, $value)
            ->when($member !== null, fn (Builder $query): Builder => $query->whereKeyNot($member->getKey()))
            ->exists();

        return $taken ? null : $value;
    }

    /**
     * The federation overwrites what it owns; the club keeps everything a human
     * may have touched.
     *
     * The licence number and the postal address are the federation's: it issues
     * one and takes the affiliation out on the other, so whatever the club typed
     * in gives way. That is the point of importing the listing rather than
     * reading it — a club-side number that contradicts the file is a club-side
     * mistake, and the reviewer already saw the difference reported before
     * choosing to update.
     *
     * Names and gender are never rewritten: the reviewer corrected them by hand,
     * and next year's file holds the same raw string. The email address and the
     * phone number are only filled when the club has none — an address is a
     * login, the listing still shows a parent's against a child years after the
     * club gave that child one of their own, and overwriting would merge back
     * the two accounts that were just separated. The address goes through
     * {@see loginAddress()} exactly as it does on creation: an update is not a
     * way around the one-address-one-login rule.
     *
     * A contradicting birthdate is not settled here at all: the matcher reports
     * it, a human decides, because it can just as well mean this line was
     * matched to the wrong person.
     */
    private static function update(ImportLine $line): ?User
    {
        $member = User::withTrashed()->find($line->existingUserId);

        if ($member === null) {
            return null;
        }

        $row = $line->row;
        $minor = self::isMinor($row);

        $member->fill([
            'ranking' => $row->ranking,
            'federation_licence_type' => $row->federationLicenceType,
            'federation_synced_at' => now(),
        ]);

        self::overwrite($member, 'licence', self::unlessTaken('licence', $row->licence === '' ? null : $row->licence, $member));
        self::overwrite($member, 'street', $row->street);
        self::overwrite($member, 'city_code', $row->cityCode);
        self::overwrite($member, 'city_name', $row->cityName);

        self::fillIfMissing($member, 'email', self::loginAddress($line, $member));
        self::fillIfMissing($member, 'birthdate', $row->birthdate);
        self::fillIfMissing($member, $minor ? 'guardian_phone_number' : 'phone_number', $row->phone);

        $member->save();

        // An archived member listed as affiliated again has come back. The review
        // screen never proposes this on its own — an archived file is one of the
        // two answers it hands over undecided — so reaching here means a human
        // went looking for that file and pointed at it.
        if ($member->trashed()) {
            $member->restore();
        }

        return $member;
    }
}
