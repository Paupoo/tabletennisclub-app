<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationAvailability;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Mutuality;
use App\Domains\Shared\Enums\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const ATTESTATION_ADMIN = 'pages::club-admin.attestations.index';

beforeEach(function (): void {
    Storage::fake('local');
    Club::factory()->ownClub()->create(['phone_contact' => null]);

    $this->office = User::factory()->withRole(Role::ATTESTATIONS)->create();
    $this->membersDelegate = User::factory()->withRole(Role::MEMBERS)->create();
});

it('is closed to the members delegate', function (): void {
    $this->actingAs($this->membersDelegate)
        ->get(route('admin.attestations.index'))
        ->assertForbidden();
});

it('opens for the attestations délégation', function (): void {
    $this->actingAs($this->office)
        ->get(route('admin.attestations.index'))
        ->assertOk();
});

it('says what the club still has to provide before members can ask', function (): void {
    Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->assertSee(__('the club seal'))
        ->assertSee(__('the signature'));
});

it('writes the club phone number the Solidaris form prints', function (): void {
    Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->set('signatoryUserId', $this->office->id)
        ->set('clubPhone', '010 45 12 34')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect(Club::ourClub()->first()->phone_contact)->toBe('010 45 12 34')
        ->and(AttestationSetting::current()->signatory_user_id)->toBe($this->office->id);
});

it('refuses a seal with no transparency, and says why', function (): void {
    Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->set('sealUpload', UploadedFile::fake()->createWithContent(
            'opaque-seal.png',
            (string) file_get_contents(base_path('database/seeders/Data/attestation-specimens/opaque-seal.png')),
        ))
        ->assertHasErrors('sealUpload');

    expect(AttestationSetting::current()->seal_path)->toBeNull();
});

/*
| Picking the file is the whole gesture. The first version asked for a second
| click on a button labelled "replace", which read as a way to choose a
| different file — six uploads sat in the Livewire buffer while the screen went
| on reporting the seal as missing.
*/
it('saves the seal the moment it is picked, with no second click', function (): void {
    Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->set('sealUpload', UploadedFile::fake()->createWithContent(
            'specimen-seal.png',
            (string) file_get_contents(base_path('database/seeders/Data/attestation-specimens/specimen-seal.png')),
        ))
        ->assertHasNoErrors()
        ->assertSet('sealUpload', null);

    expect(AttestationSetting::current()->seal_path)->not->toBeNull();
});

it('saves the signature the same way, and opens the gate once both are in', function (): void {
    $component = Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->set('signatoryUserId', $this->office->id)
        ->call('saveSettings')
        ->set('sealUpload', UploadedFile::fake()->createWithContent(
            'specimen-seal.png',
            (string) file_get_contents(base_path('database/seeders/Data/attestation-specimens/specimen-seal.png')),
        ))
        ->set('signatureUpload', UploadedFile::fake()->createWithContent(
            'specimen-signature.png',
            (string) file_get_contents(base_path('database/seeders/Data/attestation-specimens/specimen-signature.png')),
        ));

    $component->assertHasNoErrors();

    expect(app(AttestationAvailability::class)->isReady())
        ->toBeTrue();
});

it('installs a published form and reports that every label was found', function (): void {
    Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->set('templateFor', Mutuality::Partenamut->value)
        ->set('templateUpload', UploadedFile::fake()->createWithContent(
            'partenamut.pdf',
            (string) file_get_contents(base_path('database/seeders/Data/attestation-templates/partenamut.pdf')),
        ))
        ->call('uploadTemplate')
        ->assertHasNoErrors();

    $template = AttestationTemplate::where('mutuality', Mutuality::Partenamut->value)->firstOrFail();

    expect($template->unresolved_fields)->toBe([])
        ->and($template->isUsable())->toBeTrue();
});

it('lists what the club has certified', function (): void {
    $attestation = MutualAttestation::factory()->create(['reference' => 'ATT-2627-00042']);

    Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->assertSee('ATT-2627-00042')
        ->assertSee($attestation->user->full_name);
});

it('will not revoke without a reason', function (): void {
    $attestation = MutualAttestation::factory()->create();

    Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->set('revoking', $attestation->id)
        ->call('revoke')
        ->assertHasErrors('revocationReason');

    expect($attestation->fresh()->isRevoked())->toBeFalse();
});

it('revokes with a reason, and frees the season', function (): void {
    $attestation = MutualAttestation::factory()->create();

    Livewire::actingAs($this->office)
        ->test(ATTESTATION_ADMIN)
        ->set('revoking', $attestation->id)
        ->set('revocationReason', 'Mutuelle erronée')
        ->call('revoke')
        ->assertHasNoErrors();

    expect($attestation->fresh()->isRevoked())->toBeTrue()
        ->and($attestation->fresh()->revocation_reason)->toBe('Mutuelle erronée');
});
