<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('user can update their own contact fields', function (): void {
    $user = User::factory()->create([
        'email' => 'original@example.com',
        'phone_number' => '0470000000',
    ]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('email', 'updated@example.com')
        ->set('phone_number', '0479999999')
        ->call('save');

    expect($user->fresh()->email)->toBe('updated@example.com')
        ->and($user->fresh()->phone_number)->toBe('0479999999');
});

test('user can update identity fields', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
    ]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('first_name', 'Pierre')
        ->set('last_name', 'Martin')
        ->call('save');

    expect($user->fresh()->first_name)->toBe('Pierre')
        ->and($user->fresh()->last_name)->toBe('Martin');
});

test('user can update gender and birthdate', function (): void {
    $user = User::factory()->create(['gender' => Gender::MEN->value]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', '2000-05-15')
        ->call('save');

    expect($user->fresh()->gender)->toBe(Gender::WOMEN)
        ->and($user->fresh()->birthdate->format('Y-m-d'))->toBe('2000-05-15');
});

test('user can upload a medical certificate', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('medicalCertificate', UploadedFile::fake()->create('cert.pdf', 200, 'application/pdf'))
        ->call('save');

    $path = $user->fresh()->medical_certificate_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $path));
});

test('user can upload a parental consent', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('parentalConsent', UploadedFile::fake()->create('consent.pdf', 200, 'application/pdf'))
        ->call('save');

    $path = $user->fresh()->parental_consent_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $path));
});

test('parental consent field is shown for minors', function (): void {
    $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);

    Livewire::actingAs($minor)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $minor])
        ->assertSee(__('Parental consent'));
});

test('parental consent field is hidden for adults', function (): void {
    $adult = User::factory()->create(['birthdate' => now()->subYears(30)]);

    Livewire::actingAs($adult)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $adult])
        ->assertDontSee(__('Parental consent'));
});

test('parental consent field appears reactively when birthdate becomes minor', function (): void {
    $adult = User::factory()->create(['birthdate' => now()->subYears(30)]);

    Livewire::actingAs($adult)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $adult])
        ->assertDontSee(__('Parental consent'))
        ->set('birthdate', now()->subYears(14)->format('Y-m-d'))
        ->assertSee(__('Parental consent'));
});

test('user cannot update another users profile', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'other@example.com']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $other])
        ->set('email', 'hacked@example.com')
        ->call('save');

    expect($other->fresh()->email)->toBe('other@example.com');
});

test('admin can update any users profile', function (): void {
    $admin = $this->createFakeAdmin();
    $user = User::factory()->create(['phone_number' => '0470000000']);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('phone_number', '0479111111')
        ->call('save');

    expect($user->fresh()->phone_number)->toBe('0479111111');
});

test('email must be unique across users', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('email', 'taken@example.com')
        ->call('save')
        ->assertHasErrors(['email']);
});

test('user can request GDPR erasure', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->call('requestErasure');

    expect($user->fresh())->not->toBeNull();
});
