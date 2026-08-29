<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\UpdateUserData;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserAction
{
    public static function handle(User $user, UpdateUserData $data, User $actor): User
    {
        $emailChanged = $data->email !== $user->email;

        $attributes = [
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'gender' => $data->gender,
            'phone_number' => $data->phone_number,
            'street' => $data->street,
            'city_code' => $data->city_code,
            'city_name' => $data->city_name,
            'birthdate' => $data->birthdate,
            'guardian_phone_number' => $data->guardian_phone_number,
            'iban' => $data->iban,
            'has_key' => $data->has_key,
            'licence' => $data->licence,
            'ranking' => $data->ranking ?? 'NA',
            // committee_role is deliberately absent: the statutory title is a
            // right, written by SyncUserAccessAction alongside the seat it
            // belongs to, never by whoever edits the member's data.
            'updated_by' => $actor->id,
        ];

        if ($data->password !== null && $data->password !== '') {
            $attributes['password'] = Hash::make($data->password);
        }

        $user->update($attributes);

        SyncUserAccessAction::handle($user, $data->access, $actor);

        $user->guardians()->sync($data->guardianIds);
        SyncFamilyGroupMembersAction::handle($user, $data->familyMemberIds);

        // A changed email must be re-verified (email_verified_at is not mass-assignable).
        // Nulling the timestamp is the enforcement (the `verified` middleware blocks
        // access until re-verified); the courtesy email is best-effort and must never
        // break the update if mail delivery is unavailable.
        //
        // An address taken away is a change like any other for the timestamp, but
        // there is nowhere left to write to: the member has become a managed account,
        // reached through their guardian.
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();

            if ($data->email !== null) {
                rescue(fn () => $user->sendEmailVerificationNotification(), report: false);
            }
        }

        return $user;
    }
}
