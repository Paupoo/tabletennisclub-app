<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\UpdateUserData;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
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
            'committee_role' => $data->committee_role,
            'updated_by' => $actor->id,
        ];

        if ($data->password !== null && $data->password !== '') {
            $attributes['password'] = Hash::make($data->password);
        }

        $user->update($attributes);

        SyncBaseRolesAction::handle($user, $data->is_admin, $data->is_committee_member, $data->is_coach);

        $user->guardians()->sync($data->guardianIds);
        SyncFamilyGroupMembersAction::handle($user, $data->familyMemberIds);

        $seasonId = Season::current()?->id;
        if ($seasonId !== null) {
            Subscription::where('user_id', $user->id)
                ->where('season_id', $seasonId)
                ->update(['is_competitive' => $data->is_competitor]);
        }

        // A changed email must be re-verified (email_verified_at is not mass-assignable).
        // Nulling the timestamp is the enforcement (the `verified` middleware blocks
        // access until re-verified); the courtesy email is best-effort and must never
        // break the update if mail delivery is unavailable.
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
            rescue(fn () => $user->sendEmailVerificationNotification(), report: false);
        }

        return $user;
    }
}
