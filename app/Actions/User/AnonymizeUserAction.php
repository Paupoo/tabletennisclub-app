<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;

class AnonymizeUserAction
{
    public static function handle(User $user): void
    {
        // Physically remove private documents (health/minor data), not just the paths.
        StoreUserDocumentAction::deleteExisting($user, 'medical');
        StoreUserDocumentAction::deleteExisting($user, 'parental_consent');

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
