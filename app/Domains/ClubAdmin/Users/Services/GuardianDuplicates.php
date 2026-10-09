<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Services;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Rules\ValidPhone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Responsible adults the club has on file twice.
 *
 * A sheet typed by hand may describe a person the club already knows: another
 * sheet created for a brother before duplicates were caught, or a member whose
 * address it carries. Nothing is stored about it — the pair is read again from
 * the sheets every time, so a merge or a correction makes it disappear.
 *
 * Only a guardian with no account is ever a duplicate: one who holds an account
 * is identified by it.
 */
class GuardianDuplicates
{
    /** @var Collection<int, Guardian>|null */
    private ?Collection $externals = null;

    /**
     * The person this sheet is already on file as: the member whose address it
     * carries, or the oldest other sheet sharing its address or phone number.
     */
    public function counterpartOf(Guardian $guardian): Guardian|User|null
    {
        if ($guardian->hasAccount()) {
            return null;
        }

        $email = $this->normalizeEmail($guardian->email);

        if ($email !== null) {
            // A ward carrying their parent's address is a legacy of shared
            // mailboxes, not the parent: a guardian is never their own ward.
            $member = User::query()
                ->where(DB::raw('LOWER(TRIM(email))'), $email)
                ->whereNotIn('id', $guardian->users()->pluck('users.id'))
                ->first();

            if ($member instanceof User) {
                return $member;
            }
        }

        $phone = ValidPhone::normalize($guardian->phone);

        return $this->externals()->first(fn (Guardian $other): bool => $other->id !== $guardian->id && (
            ($email !== null && $this->normalizeEmail($other->email) === $email)
            || ($phone !== null && ValidPhone::normalize($other->phone) === $phone)
        ));
    }

    /**
     * Every sheet to fold into another, one per extra copy: three sheets for
     * the same mother are two to merge, not three.
     *
     * @return Collection<int, Guardian>
     */
    public function toMerge(): Collection
    {
        return $this->externals()->filter(function (Guardian $guardian): bool {
            $counterpart = $this->counterpartOf($guardian);

            return $counterpart instanceof User
                || ($counterpart instanceof Guardian && $counterpart->id < $guardian->id);
        })->values();
    }

    /**
     * The sheets of guardians with no account, oldest first. The table holds one
     * row per guardian of the club, so reading it whole stays cheap.
     *
     * @return Collection<int, Guardian>
     */
    private function externals(): Collection
    {
        return $this->externals ??= Guardian::query()->whereNull('user_id')->with('users')->orderBy('id')->get();
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }
}
