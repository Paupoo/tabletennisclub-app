<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function activeOpenSeason(): Season
{
    return Season::factory()->create([
        'is_active' => true,
        'affiliations_open' => true,
    ]);
}

describe('Registration management — season attributes', function (): void {
    it('persists the season attributes entered by the member on affiliation', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);
        $season = activeOpenSeason();

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
            ->set("registrations.{$user->id}.formula", 'competitive')
            ->set("registrations.{$user->id}.can_drive", true)
            ->set("registrations.{$user->id}.seats_available", 3)
            ->set("registrations.{$user->id}.wants_to_be_captain", true)
            ->set("registrations.{$user->id}.volunteer_help", true)
            ->set("registrations.{$user->id}.wants_directed_training", true)
            ->call('confirmAffiliation', $user->id);

        $subscription = Subscription::where('user_id', $user->id)
            ->where('season_id', $season->id)
            ->first();

        expect($subscription)->not->toBeNull()
            ->and($subscription)
            ->is_competitive->toBeTrue()
            ->can_drive->toBeTrue()
            ->seats_available->toBe(3)
            ->wants_to_be_captain->toBeTrue()
            ->volunteer_help->toBeTrue()
            ->wants_directed_training->toBeTrue();
    });

    it('defaults the season attributes when the member leaves them untouched', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);
        $season = activeOpenSeason();

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
            ->call('confirmAffiliation', $user->id);

        $subscription = Subscription::where('user_id', $user->id)
            ->where('season_id', $season->id)
            ->first();

        expect($subscription)->not->toBeNull()
            ->and($subscription)
            ->can_drive->toBeFalse()
            ->seats_available->toBeNull()
            ->wants_to_be_captain->toBeFalse()
            ->volunteer_help->toBeFalse()
            ->wants_directed_training->toBeFalse();
    });

    it('clears seats_available when can_drive is turned off', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);
        $season = activeOpenSeason();

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
            ->set("registrations.{$user->id}.can_drive", true)
            ->set("registrations.{$user->id}.seats_available", 4)
            ->set("registrations.{$user->id}.can_drive", false)
            ->call('confirmAffiliation', $user->id);

        $subscription = Subscription::where('user_id', $user->id)
            ->where('season_id', $season->id)
            ->first();

        expect($subscription)->not->toBeNull()
            ->and($subscription->can_drive)->toBeFalse()
            ->and($subscription->seats_available)->toBeNull();
    });
})->group('subscriptions');

describe('Registration management — carry-over seed', function (): void {
    it('pre-fills the registration from the originating contact seed', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);
        activeOpenSeason();

        Contact::factory()->withProfile()->create([
            'user_id' => $user->id,
            'wants_competition' => true,
            'family_can_drive' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user]);

        expect($component->get('registrations')[$user->id]['formula'])->toBe('competitive')
            ->and($component->get('registrations')[$user->id]['can_drive'])->toBeTrue();
    });

    it('falls back to neutral defaults when the contact seed omits a key', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);
        activeOpenSeason();

        // Contact linked but no profile fields at all → empty seed.
        Contact::factory()->create([
            'user_id' => $user->id,
            'wants_competition' => null,
            'family_can_drive' => null,
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user]);

        expect($component->get('registrations')[$user->id]['formula'])->toBe('recreative')
            ->and($component->get('registrations')[$user->id]['can_drive'])->toBeFalse();
    });

    it('does not override an existing subscription with the contact seed', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);
        $season = activeOpenSeason();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => false,
            'status' => 'pending',
        ]);

        Contact::factory()->create([
            'user_id' => $user->id,
            'wants_competition' => true,
            'family_can_drive' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user]);

        // Existing subscription (recreative) wins; seed is ignored.
        expect($component->get('registrations')[$user->id]['formula'])->toBe('recreative')
            ->and($component->get('registrations')[$user->id]['can_drive'])->toBeFalse();
    });
})->group('subscriptions');
