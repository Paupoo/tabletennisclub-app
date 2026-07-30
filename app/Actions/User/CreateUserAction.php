<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\CreateUserData;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public static function handle(CreateUserData $data, User $actor): User
    {
        $hasPassword = $data->password !== null && $data->password !== '';

        $user = User::create([
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'gender' => $data->gender,
            'phone_number' => $data->phone_number,
            'street' => $data->street,
            'city_code' => $data->city_code,
            'city_name' => $data->city_name,
            'birthdate' => $data->birthdate,
            'has_key' => $data->has_key,
            'licence' => $data->licence,
            'ranking' => $data->ranking ?? 'NA',
            'committee_role' => $data->committee_role,
            'updated_by' => $actor->id,
            'password' => $hasPassword ? Hash::make($data->password) : '',
        ]);

        SyncUserRolesAction::handle($user, $data->is_admin, $data->is_committee_member, $data->delegations);

        if ($data->guardianIds !== []) {
            $user->guardians()->sync($data->guardianIds);
        }

        SyncFamilyGroupMembersAction::handle($user, $data->familyMemberIds);

        // Invitation flow only when the admin did not set a password directly.
        if (! $hasPassword) {
            SendInvitationAction::handle($user);
        }

        return $user;
    }
}
