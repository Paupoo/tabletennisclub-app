<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Users;

use App\Data\User\FederationRow;
use App\Data\User\MemberMatch;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MemberMatchOutcome;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Decides whether an affiliate in the federation listing is somebody the club
 * already knows.
 *
 * The club roster is not a copy of the federation's: members were typed in by
 * hand over the years, some without a licence number, some under an address the
 * federation never had. Identity is therefore established by a cascade, strongest
 * evidence first, and anything short of proof is handed to a human instead of
 * being guessed — a wrong match silently overwrites a real person.
 *
 * Comparisons happen in PHP rather than in SQL on purpose. MySQL's
 * `utf8mb4_unicode_ci` collation ignores case and accents, SQLite does not, and
 * the test suite runs on SQLite: matching in the database would mean the rule
 * being verified is not the rule that ships.
 */
class FederationMemberMatcher
{
    /**
     * The roster, read once and reused for every row of a listing. Archived
     * members are included: one who comes back must be offered for restoration,
     * never duplicated.
     *
     * @var Collection<int, User>|null
     */
    private ?Collection $roster = null;

    public function match(FederationRow $row): MemberMatch
    {
        $existing = $this->byLicence($row)
            ?? $this->byEmail($row)
            ?? $this->byNameAndBirthdate($row);

        if ($existing !== null) {
            return new MemberMatch(
                row: $row,
                outcome: $existing->trashed() ? MemberMatchOutcome::ARCHIVED : MemberMatchOutcome::MATCHED,
                existing: $existing,
                discrepancies: $this->discrepancies($row, $existing),
            );
        }

        $namesake = $this->namesake($row);

        if ($namesake !== null) {
            return new MemberMatch(
                row: $row,
                outcome: MemberMatchOutcome::SUSPECT,
                existing: $namesake,
                discrepancies: $this->discrepancies($row, $namesake),
            );
        }

        return new MemberMatch(row: $row, outcome: MemberMatchOutcome::NEW);
    }

    /**
     * One address, one account: a hit is proof.
     */
    private function byEmail(FederationRow $row): ?User
    {
        if ($row->email === null) {
            return null;
        }

        $email = $this->normalize($row->email);

        return $this->roster()->first(
            fn (User $user): bool => $user->email !== null && $this->normalize($user->email) === $email
        );
    }

    /**
     * The licence number is the federation's own identifier, and unique on our
     * side too, so a hit is proof.
     */
    private function byLicence(FederationRow $row): ?User
    {
        if ($row->licence === '') {
            return null;
        }

        return $this->roster()->first(
            fn (User $user): bool => $user->licence === $row->licence
        );
    }

    /**
     * Last resort for the members typed in by hand, with neither licence nor the
     * address the federation holds.
     *
     * A birthdate missing on our side never takes part: seven members were
     * recorded without one, and treating that as a match would hand each of them
     * the first namesake the federation sends.
     */
    private function byNameAndBirthdate(FederationRow $row): ?User
    {
        if ($row->birthdate === null) {
            return null;
        }

        return $this->roster()->first(
            fn (User $user): bool => $user->birthdate !== null
                && $user->birthdate->isSameDay($row->birthdate)
                && $this->sameName($user, $row)
        );
    }

    /**
     * Only reported when the club holds an address of its own to contradict:
     * filling an empty field is not a disagreement.
     */
    private function differentAddress(FederationRow $row, User $existing): bool
    {
        $held = [$existing->street, $existing->city_code, $existing->city_name];

        if (array_filter($held) === []) {
            return false;
        }

        return $this->normalize($existing->street) !== $this->normalize($row->street)
            || $this->normalize($existing->city_code) !== $this->normalize($row->cityCode)
            || $this->normalize($existing->city_name) !== $this->normalize($row->cityName);
    }

    /**
     * What the federation says and the club does not, on fields the club owns.
     *
     * @return array<int, string>
     */
    private function discrepancies(FederationRow $row, User $existing): array
    {
        $discrepancies = [];

        if ($row->licence !== '' && $existing->licence !== null && $existing->licence !== $row->licence) {
            $discrepancies[] = 'licence';
        }

        if ($row->birthdate !== null && $existing->birthdate !== null
            && ! $existing->birthdate->isSameDay($row->birthdate)) {
            $discrepancies[] = 'birthdate';
        }

        if ($row->email !== null && $existing->email !== null
            && $this->normalize($existing->email) !== $this->normalize($row->email)) {
            $discrepancies[] = 'email';
        }

        if ($this->differentAddress($row, $existing)) {
            $discrepancies[] = 'address';
        }

        return $discrepancies;
    }

    /**
     * Somebody carrying this name whose birthdate says otherwise — a namesake, a
     * mistyped date, or a member recorded without one. All three are questions.
     */
    private function namesake(FederationRow $row): ?User
    {
        return $this->roster()->first(
            fn (User $user): bool => $this->sameName($user, $row)
        );
    }

    /**
     * Casing, accents and stray spacing are presentation, not identity.
     */
    private function normalize(?string $value): string
    {
        return mb_strtolower(trim(Str::ascii((string) $value)));
    }

    /**
     * @return Collection<int, User>
     */
    private function roster(): Collection
    {
        return $this->roster ??= User::withTrashed()->get();
    }

    private function sameName(User $user, FederationRow $row): bool
    {
        return $this->normalize($user->last_name) === $this->normalize($row->lastName)
            && $this->normalize($user->first_name) === $this->normalize($row->firstName);
    }
}
