<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
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

test('completing the identity step persists the fields and advances to address for an adult', function (): void {
    $user = incompleteUser();

    Livewire::actingAs($user)
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', '1990-05-15')
        ->set('phone_number', '0470 12 34 56')
        ->call('completeIdentityStep')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    $user->refresh();
    expect($user->gender)->toBe(Gender::WOMEN)
        ->and($user->birthdate->format('Y-m-d'))->toBe('1990-05-15')
        ->and($user->phone_number)->toBe('0470 12 34 56');
});

test('completing the identity step advances to the guardian step for a minor', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
        ->set('phone_number', '0470 12 34 56')
        ->call('completeIdentityStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

test('the address step requires street, postal code and city', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 3)
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
        ->assertSet('step', 4);

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
        ->set('step', 4)
        ->assertSee(__('Parental consent'));
});

test('the parental consent upload is hidden for adults on the last step', function (): void {
    $adult = incompleteUser();

    Livewire::actingAs($adult)
        ->test(ONBOARDING_COMPONENT)
        ->set('birthdate', now()->subYears(30)->format('Y-m-d'))
        ->set('step', 4)
        ->assertDontSee(__('Parental consent'));
});

test('a member with a complete profile resumes on the optional step', function (): void {
    $user = User::factory()->create();

    expect($user->hasCompleteProfile())->toBeTrue();

    Livewire::actingAs($user)
        ->test(ONBOARDING_COMPONENT)
        ->assertSet('step', 4)
        ->assertSet('maxReachable', 4);
});

test('an unreachable step cannot be forced through goToStep', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->call('goToStep', 4)
        ->assertSet('step', 1);
});

test('the guardian step is not reachable for an adult via goToStep', function (): void {
    $user = incompleteUser();

    Livewire::actingAs($user)
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::MEN)
        ->set('birthdate', now()->subYears(30)->format('Y-m-d'))
        ->set('phone_number', '0470 00 00 00')
        ->call('completeIdentityStep')
        ->assertSet('step', 3)
        ->call('goToStep', 2)
        ->assertSet('step', 3);
});

test('a minor is blocked from advancing past the guardian step without a guardian', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
        ->set('phone_number', '0470 12 34 56')
        ->call('completeIdentityStep')
        ->call('completeGuardianStep')
        ->assertSet('step', 2)
        ->assertSet('guardianIds', []);
});

test('linking an existing member as guardian allows the minor to advance and persists the link', function (): void {
    $adult = User::factory()->create(['first_name' => 'Paul', 'last_name' => 'Durand']);
    $minor = incompleteUser();

    Livewire::actingAs($minor)
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
        ->set('phone_number', '0470 12 34 56')
        ->call('completeIdentityStep')
        ->call('attachMemberAsGuardian', $adult->id)
        ->call('completeGuardianStep')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    expect($minor->fresh()->hasGuardian())->toBeTrue();
});

test('creating a new guardian allows the minor to advance and persists the link', function (): void {
    $minor = incompleteUser();

    Livewire::actingAs($minor)
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
        ->set('phone_number', '0470 12 34 56')
        ->call('completeIdentityStep')
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0479123456')
        ->set('guardianEmail', 'marie.dupont@example.com')
        ->call('createGuardian')
        ->call('completeGuardianStep')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    expect($minor->fresh()->hasGuardian())->toBeTrue();
});

test('a minor who completes the whole wizard is not sent back to the guardian step', function (): void {
    $minor = incompleteUser();

    Livewire::actingAs($minor)
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
        ->set('phone_number', '0470 12 34 56')
        ->call('completeIdentityStep')
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0479123456')
        ->set('guardianEmail', 'marie.dupont@example.com')
        ->call('createGuardian')
        ->call('completeGuardianStep')
        ->set('street', 'Rue de la Station 1')
        ->set('city_code', '1340')
        ->set('city_name', 'Ottignies')
        ->call('completeAddressStep')
        ->call('finish')
        ->assertHasNoErrors();

    $minor->refresh();
    expect($minor->requiresGuardian())->toBeFalse()
        ->and($minor->hasCompleteProfile())->toBeTrue();

    $this->actingAs($minor)
        ->get(route('admin.user.profile', $minor))
        ->assertSuccessful();
});

test('a minor who onboarded before the guardian requirement is resumed on the guardian step', function (): void {
    $minor = User::factory()->minor()->create();

    $this->actingAs($minor)
        ->get(route('admin.user.profile', $minor))
        ->assertRedirect(route('admin.user.onboarding'));

    Livewire::actingAs($minor)
        ->test(ONBOARDING_COMPONENT)
        ->assertSet('step', 2);
});

test('the guardian step opens on the creation form and explains why the club asks', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
        ->set('phone_number', '0470 12 34 56')
        ->call('completeIdentityStep')
        ->assertSet('showGuardianForm', true)
        ->assertSet('showGuardianSearch', false)
        ->assertSee(__('You are under 18: the club needs a parent or guardian it can reach for authorisations and payment reminders.'))
        ->assertSee(__('Already a club member, or a guardian of another player?'))
        ->assertDontSee(__('Find an existing guardian or member'));
});

test('the search is unfolded on demand as the fallback path', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->set('showGuardianSearch', true)
        ->assertSee(__('Find an existing guardian or member'));
});

test('the guardian email is required during onboarding', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0479123456')
        ->call('createGuardian')
        ->assertHasErrors(['guardianEmail' => 'required'])
        ->assertSet('guardianIds', []);

    expect(Guardian::count())->toBe(0);
});

test('the guardian phone must look like a phone number', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianEmail', 'marie.dupont@example.com')
        ->set('guardianPhone', 'azerty')
        ->call('createGuardian')
        ->assertHasErrors(['guardianPhone']);

    expect(Guardian::count())->toBe(0);
});

test('a guardian field is validated as soon as the member leaves it', function (): void {
    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->set('guardianPhone', 'azerty')
        ->assertHasErrors(['guardianPhone'])
        ->set('guardianPhone', '0479 12 34 56')
        ->assertHasNoErrors('guardianPhone');
});

test('an existing guardian with the same email is offered for linking instead of duplicated', function (): void {
    $existing = Guardian::factory()->create([
        'first_name' => 'Marie',
        'last_name' => 'Dupont',
        'email' => 'marie.dupont@example.com',
        'phone' => '0479123456',
    ]);

    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0470000000')
        ->set('guardianEmail', 'MARIE.DUPONT@example.com')
        ->call('createGuardian')
        ->assertSet('duplicateGuardianId', $existing->id)
        ->assertSet('guardianIds', [])
        ->assertSee(__('Link this guardian'))
        ->call('linkDuplicateGuardian')
        ->assertSet('guardianIds', [$existing->id])
        ->assertSet('duplicateGuardianId', null);

    expect(Guardian::count())->toBe(1);
});

test('an existing guardian with the same phone written differently is detected', function (): void {
    $existing = Guardian::factory()->create([
        'first_name' => 'Marie',
        'last_name' => 'Dupont',
        'email' => 'marie.dupont@example.com',
        'phone' => '+32 479 12 34 56',
    ]);

    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0479 12 34 56')
        ->set('guardianEmail', 'autre.adresse@example.com')
        ->call('createGuardian')
        ->assertSet('duplicateGuardianId', $existing->id)
        ->assertSee(__('Link this guardian'));

    expect(Guardian::count())->toBe(1);
});

test('a guardian already linked to the member is reported as such, with no link offered', function (): void {
    $minor = incompleteUser();
    $existing = Guardian::factory()->create([
        'first_name' => 'Marie',
        'last_name' => 'Dupont',
        'email' => 'marie.dupont@example.com',
        'phone' => '0479123456',
    ]);
    $minor->guardians()->attach($existing);

    Livewire::actingAs($minor)
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->set('showGuardianForm', true)
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0479123456')
        ->set('guardianEmail', 'marie.dupont@example.com')
        ->call('createGuardian')
        ->assertSet('duplicateGuardianId', $existing->id)
        ->assertSee(__(':name is already one of the guardians linked here — nothing more to do.', ['name' => 'Marie Dupont']))
        ->assertDontSee(__('Link this guardian'))
        ->assertSet('guardianIds', [$existing->id]);

    expect(Guardian::count())->toBe(1);
});

test('changing the details after a duplicate warning clears it', function (): void {
    Guardian::factory()->create([
        'email' => 'marie.dupont@example.com',
        'phone' => '0479123456',
    ]);

    Livewire::actingAs(incompleteUser())
        ->test(ONBOARDING_COMPONENT)
        ->set('step', 2)
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0479123456')
        ->set('guardianEmail', 'marie.dupont@example.com')
        ->call('createGuardian')
        ->assertNotSet('duplicateGuardianId', null)
        ->set('guardianEmail', 'nouvelle.adresse@example.com')
        ->assertSet('duplicateGuardianId', null);
});
