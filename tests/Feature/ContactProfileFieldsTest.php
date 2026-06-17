<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\PlayerExperienceEnum;

it('persists and casts the optional triage profile fields', function (): void {
    $contact = Contact::factory()->create([
        'age_category' => AgeCategoryEnum::TEEN,
        'experience' => PlayerExperienceEnum::FEW_MONTHS,
        'wants_competition' => true,
        'preferred_days' => ['monday', 'wednesday'],
        'family_can_drive' => false,
    ]);

    $fresh = $contact->fresh();

    expect($fresh->age_category)->toBe(AgeCategoryEnum::TEEN)
        ->and($fresh->experience)->toBe(PlayerExperienceEnum::FEW_MONTHS)
        ->and($fresh->wants_competition)->toBeTrue()
        ->and($fresh->preferred_days)->toBe(['monday', 'wednesday'])
        ->and($fresh->family_can_drive)->toBeFalse();
});

it('leaves the triage profile fields nullable by default', function (): void {
    $contact = Contact::factory()->create();

    $fresh = $contact->fresh();

    expect($fresh->age_category)->toBeNull()
        ->and($fresh->experience)->toBeNull()
        ->and($fresh->wants_competition)->toBeNull()
        ->and($fresh->preferred_days)->toBeNull()
        ->and($fresh->family_can_drive)->toBeNull()
        ->and($fresh->user_id)->toBeNull();
});

it('can be linked to a user', function (): void {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['user_id' => $user->id]);

    expect($contact->user->is($user))->toBeTrue();
});

it('exposes a withProfile factory state', function (): void {
    $contact = Contact::factory()->withProfile()->create();

    expect($contact->age_category)->toBeInstanceOf(AgeCategoryEnum::class)
        ->and($contact->experience)->toBeInstanceOf(PlayerExperienceEnum::class)
        ->and($contact->preferred_days)->toBeArray();
});
