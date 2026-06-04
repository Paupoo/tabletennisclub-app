<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;

class AnonymizeUserAction
{
    public static function handle(User $user): void
    {
        $user->update([
            'first_name' => 'Anonymized',
            'last_name' => 'User',
            'email' => "deleted-{$user->id}@anonymous.local",
            'phone_number' => null,
            'guardian_phone_number' => null,
            'street' => null,
            'city_code' => null,
            'city_name' => null,
            'birthdate' => null,
            'iban' => null,
            'photo' => null,
            'avatar_url' => null,
            'medical_certificate_path' => null,
            'parental_consent_path' => null,
            'password' => '',
            'remember_token' => null,
        ]);

        $user->guardians()->detach();

        $user->delete();
    }
}
