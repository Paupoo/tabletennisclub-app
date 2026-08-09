<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\CreateUserData;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;

class OnboardFromContactAction
{
    public static function handle(Contact $contact, User $actor): User
    {
        $data = new CreateUserData(
            first_name: $contact->first_name,
            last_name: $contact->last_name,
            email: $contact->email,
            gender: Gender::MEN,
            phone_number: $contact->phone ?? null,
        );

        $user = CreateUserAction::handle($data, $actor);

        $contact->update(['status' => 'processed', 'user_id' => $user->id]);

        return $user;
    }

    public static function linkToExisting(Contact $contact, User $existingUser): User
    {
        $contact->update(['status' => 'processed', 'user_id' => $existingUser->id]);

        return $existingUser;
    }

    public static function matchExistingUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
    }

    public static function matchTrashedUser(string $email): ?User
    {
        return User::onlyTrashed()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
    }
}
