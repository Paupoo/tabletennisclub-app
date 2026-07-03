<?php

declare(strict_types=1);

use App\Actions\User\StoreUserDocumentAction;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

/**
 * Creates a user with a stored private medical certificate.
 */
function createUserWithMedicalCertificate(): User
{
    $user = User::factory()->create();
    $path = "documents/{$user->id}/medical.pdf";

    Storage::disk('local')->put($path, '%PDF-1.4 fake');
    $user->update(['medical_certificate_path' => $path]);

    return $user;
}

beforeEach(function (): void {
    Storage::fake('local');
});

test('a member can download their own medical certificate', function (): void {
    $user = createUserWithMedicalCertificate();

    $this->actingAs($user)
        ->get(route('admin.user.documents.download', [$user, 'medical']))
        ->assertSuccessful();
});

test('an admin can download a members document', function (): void {
    $user = createUserWithMedicalCertificate();

    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.user.documents.download', [$user, 'medical']))
        ->assertSuccessful();
});

test('a committee member can download a members document', function (): void {
    $user = createUserWithMedicalCertificate();

    $this->actingAs($this->createFakeCommitteeMember())
        ->get(route('admin.user.documents.download', [$user, 'medical']))
        ->assertSuccessful();
});

test('a member guardian can download their wards document', function (): void {
    $ward = createUserWithMedicalCertificate();
    $parent = User::factory()->create();
    $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
    $guardian->users()->attach($ward);

    $this->actingAs($parent)
        ->get(route('admin.user.documents.download', [$ward, 'medical']))
        ->assertSuccessful();
});

test('another member cannot download the document', function (): void {
    $user = createUserWithMedicalCertificate();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('admin.user.documents.download', [$user, 'medical']))
        ->assertForbidden();
});

test('a guest is redirected to login', function (): void {
    $user = createUserWithMedicalCertificate();

    $this->get(route('admin.user.documents.download', [$user, 'medical']))
        ->assertRedirect(route('login'));
});

test('an unknown document type returns 404', function (): void {
    $user = createUserWithMedicalCertificate();

    $this->actingAs($user)
        ->get(route('admin.user.documents.download', [$user, 'passport']))
        ->assertNotFound();
});

test('a missing document returns 404', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.user.documents.download', [$user, 'medical']))
        ->assertNotFound();
});

test('uploading a document stores it on the private disk, not the public one', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

    $path = StoreUserDocumentAction::handle($user, $file, 'medical');

    expect($user->fresh()->medical_certificate_path)->toBe($path)
        ->and($path)->not->toStartWith('/storage/');
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

test('replacing a legacy public document removes the publicly accessible file', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    Storage::disk('public')->put("documents/{$user->id}/medical.pdf", '%PDF-1.4 legacy');
    $user->update(['medical_certificate_path' => "/storage/documents/{$user->id}/medical.pdf"]);

    $file = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');
    StoreUserDocumentAction::handle($user, $file, 'medical');

    Storage::disk('public')->assertMissing("documents/{$user->id}/medical.pdf");
    Storage::disk('local')->assertExists($user->fresh()->medical_certificate_path);
});
