<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const ONBOARDING_COMPONENT = 'pages::club-admin.users.user-space.onboarding';

/**
 * A freshly invited member: account exists but the profile fields the club
 * requires are still empty. Gender is NOT NULL in the schema — invited users
 * get a default the wizard asks them to confirm.
 */
function incompleteUser(): User
{
    return User::factory()->create([
        'birthdate' => null,
        'phone_number' => null,
        'street' => null,
        'city_code' => null,
        'city_name' => null,
    ]);
}

test('the wizard renders for an authenticated member', function (): void {
    $this->actingAs(incompleteUser())
        ->get(route('admin.user.onboarding'))
        ->assertSuccessful()
        ->assertSee(__('Complete your profile'));
});

test('a guest is redirected to login', function (): void {
    $this->get(route('admin.user.onboarding'))
        ->assertRedirect(route('login'));
});

test('the identity step requires gender, birthdate and phone', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', null)
        ->call('completeIdentityStep')
        ->assertHasErrors(['gender', 'birthdate', 'phone_number'])
        ->assertSet('step', 1);
});

test('completing the identity step persists the fields and advances', function (): void {
    $user = incompleteUser();

    Livewire::actingAs($user)
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', '1990-05-15')
        ->set('phone_number', '0470 12 34 56')
        ->call('completeIdentityStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    $user->refresh();
    expect($user->gender)->toBe(Gender::WOMEN)
        ->and($user->birthdate->format('Y-m-d'))->toBe('1990-05-15')
        ->and($user->phone_number)->toBe('0470 12 34 56');
});

test('the address step requires street, postal code and city', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->call('completeAddressStep')
        ->assertHasErrors(['street', 'city_code', 'city_name']);
});

test('completing the address step makes the profile complete', function (): void {
    $user = incompleteUser();

    $component = Livewire::actingAs($user)
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::MEN)
        ->set('birthdate', '1985-01-01')
        ->set('phone_number', '0470 00 00 00')
        ->call('completeIdentityStep')
        ->set('street', 'Rue de la Station 1')
        ->set('city_code', '1340')
        ->set('city_name', 'Ottignies')
        ->call('completeAddressStep')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    expect($user->refresh()->hasCompleteProfile())->toBeTrue();
});

test('skipping the optional step redirects to the dashboard with a success flash', function (): void {
    $user = incompleteUser();

    Livewire::actingAs($user)
        ->test(ONBOARDING_COMPONENT)
        ->call('skipOptionalStep')
        ->assertRedirect(route('dashboard'));

    expect(session('success'))->toBe(__('Your profile is now complete. Welcome to the club!'));
});

test('finishing with optional data stores photo, iban and documents privately', function (): void {
    Storage::fake('public');
    Storage::fake('local');

    $user = incompleteUser();

    Livewire::actingAs($user)
        ->test(ONBOARDING_COMPONENT)
        ->set('iban', 'BE68539007547034')
        ->set('medicalCertificate', UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'))
        ->call('finish')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->iban)->not->toBeNull()
        ->and($user->medical_certificate_path)->not->toBeNull();
    Storage::disk('local')->assertExists($user->medical_certificate_path);
});

test('the parental consent upload is shown for minors on the last step', function (): void {
    $minor = incompleteUser();

    Livewire::actingAs($minor)
        ->test(ONBOARDING_COMPONENT)
        ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
        ->set('step', 3)
        ->assertSee(__('Parental consent'));
});

test('the parental consent upload is hidden for adults on the last step', function (): void {
    $adult = incompleteUser();

    Livewire::actingAs($adult)
        ->test(ONBOARDING_COMPONENT)
        ->set('birthdate', now()->subYears(30)->format('Y-m-d'))
        ->set('step', 3)
        ->assertDontSee(__('Parental consent'));
});

test('a member with a complete profile resumes on the optional step', function (): void {
    $user = User::factory()->create();

    expect($user->hasCompleteProfile())->toBeTrue();

    Livewire::actingAs($user)
        ->test(ONBOARDING_COMPONENT)
        ->assertSet('step', 3)
        ->assertSet('maxReachable', 3);
});

test('an unreachable step cannot be forced through goToStep', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->call('goToStep', 3)
        ->assertSet('step', 1);
});
