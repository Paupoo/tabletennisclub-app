<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fold a guardian sheet into the person the club already has on file.
 *
 * Into a member: the sheet becomes that member's, so their details are read from
 * their account and the proxy opens to them — or, when the member already holds
 * a sheet, the children move onto it. Into another sheet: the sheet being
 * corrected survives, as it carries the latest details, and takes the other's
 * children along with whatever it was missing.
 */
class MergeGuardianAction
{
    public static function handle(Guardian $sheet, Guardian|User $into): Guardian
    {
        return DB::transaction(function () use ($sheet, $into): Guardian {
            if ($into instanceof User) {
                $existing = $into->guardianRecord;

                if (! $existing instanceof Guardian) {
                    $sheet->update(['user_id' => $into->id]);

                    return $sheet;
                }

                return self::fold($sheet, $existing);
            }

            $sheet->update([
                'email' => $sheet->email ?? $into->email,
                'iban' => $sheet->iban ?? $into->iban,
            ]);

            return self::fold($into, $sheet);
        });
    }

    /** Move every child of the copy onto the survivor, then drop the copy. */
    private static function fold(Guardian $copy, Guardian $survivor): Guardian
    {
        $survivor->users()->syncWithoutDetaching($copy->users()->pluck('users.id')->all());
        $copy->users()->detach();
        $copy->delete();

        return $survivor;
    }
}
