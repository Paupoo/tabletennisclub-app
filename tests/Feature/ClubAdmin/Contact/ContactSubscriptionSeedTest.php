<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\PlayerExperienceEnum;

describe('Contact::subscriptionSeed', function (): void {
    it('maps profile fields to the subscription carry-over seed', function (): void {
        $contact = Contact::factory()->create([
            'wants_competition' => true,
            'family_can_drive' => false,
            'experience' => PlayerExperienceEnum::cases()[0],
            'age_category' => AgeCategoryEnum::cases()[0],
            'preferred_days' => ['monday', 'wednesday'],
        ]);

        expect($contact->subscriptionSeed())->toBe([
            'is_competitive' => true,
            'can_drive' => false,
            'experience' => PlayerExperienceEnum::cases()[0]->value,
            'age_category' => AgeCategoryEnum::cases()[0]->value,
            'preferred_days' => ['monday', 'wednesday'],
        ]);
    });

    it('omits keys that are not filled in', function (): void {
        $contact = Contact::factory()->create([
            'wants_competition' => null,
            'family_can_drive' => null,
            'experience' => null,
            'age_category' => null,
            'preferred_days' => null,
        ]);

        expect($contact->subscriptionSeed())->toBe([]);
    });

    it('omits is_competitive when wants_competition is null but keeps others', function (): void {
        $contact = Contact::factory()->create([
            'wants_competition' => null,
            'family_can_drive' => true,
            'experience' => null,
            'age_category' => null,
            'preferred_days' => null,
        ]);

        expect($contact->subscriptionSeed())->toBe([
            'can_drive' => true,
        ]);
    });
});

describe('User::originatingContact', function (): void {
    it('retrieves the contact linked to the user', function (): void {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);

        expect($user->originatingContact()?->is($contact))->toBeTrue();
    });

    it('returns null when no contact is linked', function (): void {
        $user = User::factory()->create();

        expect($user->originatingContact())->toBeNull();
    });
});
