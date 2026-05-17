<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe('Document upload — registration management', function () {
    it('saves medical certificate path to user after upload', function () {
        Storage::fake('public');

        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
            ->set('medicalCertificate', UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'))
            ->call('uploadMedicalCertificate', $user->id);

        $user->refresh();

        expect($user->medical_certificate_path)->not->toBeNull();
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $user->medical_certificate_path));
    });

    it('saves parental consent path to user after upload', function () {
        Storage::fake('public');

        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
            ->set('parentalConsent', UploadedFile::fake()->create('consent.pdf', 100, 'application/pdf'))
            ->call('uploadParentalConsent', $user->id);

        $user->refresh();

        expect($user->parental_consent_path)->not->toBeNull();
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $user->parental_consent_path));
    });

    it('replaces existing medical certificate when a new one is uploaded', function () {
        Storage::fake('public');

        $user = User::factory()->create([
            'birthdate' => now()->subYears(25),
            'medical_certificate_path' => '/storage/documents/' . fake()->randomNumber() . '/medical.pdf',
        ]);

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
            ->set('medicalCertificate', UploadedFile::fake()->create('new_cert.pdf', 150, 'application/pdf'))
            ->call('uploadMedicalCertificate', $user->id);

        $user->refresh();

        expect($user->medical_certificate_path)->toContain('medical.');
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $user->medical_certificate_path));
    });

    it('validates that only allowed file types are accepted for medical certificate', function () {
        Storage::fake('public');

        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
            ->set('medicalCertificate', UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'))
            ->call('uploadMedicalCertificate', $user->id)
            ->assertHasErrors(['medicalCertificate']);
    });

    it('marks user as minor when birthdate is less than 18 years ago', function () {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);

        $component = Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user]);

        expect($component->get('registrations')[$user->id]['is_minor'])->toBeTrue();
    });

    it('does not mark adult user as minor', function () {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);

        $component = Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user]);

        expect($component->get('registrations')[$user->id]['is_minor'])->toBeFalse();
    });
});
