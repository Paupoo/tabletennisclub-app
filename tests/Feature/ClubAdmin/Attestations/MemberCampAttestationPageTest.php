<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\InstallAttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationKind;
use App\Domains\Shared\Enums\Mutuality;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const CAMP_ATTESTATION_PAGE = 'pages::club-admin.users.user-space.mutual-attestation';

beforeEach(function (): void {
    Storage::fake('local');
    Mail::fake();
    Notification::fake();
    Carbon::setTestNow('2026-10-30 10:00:00');

    Club::factory()->ownClub()->create(['name' => 'C.T.T Ottignies-Blocry']);

    AttestationSetting::current()->update([
        'signatory_user_id' => User::factory()->create()->id,
        'seal_path' => base_path('database/seeders/Data/attestation-specimens/specimen-seal.png'),
        'signature_path' => base_path('database/seeders/Data/attestation-specimens/specimen-signature.png'),
    ]);

    $this->season = Season::factory()->create([
        'name' => '2026-2027',
        'is_active' => true,
        'start_at' => '2026-07-01',
        'end_at' => '2027-06-30',
    ]);

    $this->camp = campAttestationCamp($this->season);
    $this->line = campAttestationLine(campAttestationAffiliation($this->season), $this->camp);
    $this->member = $this->line->subscription->user;
});

afterEach(fn () => Carbon::setTestNow());

it('asks which document first when the member has a camp', function (): void {
    Livewire::actingAs($this->member)
        ->test(CAMP_ATTESTATION_PAGE, ['user' => $this->member])
        ->assertSet('document', '')
        ->assertSee('Affiliation 2026-2027')
        ->assertSee($this->camp->name)
        ->assertSee('5 jours');
});

it('opens straight on the affiliation for a member without a camp', function (): void {
    $member = campAttestationAffiliation($this->season)->user;

    Livewire::actingAs($member)
        ->test(CAMP_ATTESTATION_PAGE, ['user' => $member])
        ->assertSet('document', 'affiliation');
});

it('walks a family from the camp card to the club attestation', function (): void {
    Livewire::actingAs($this->member)
        ->test(CAMP_ATTESTATION_PAGE, ['user' => $this->member])
        ->call('chooseDocument', 'camp-' . $this->line->id)
        ->assertSet('step', 1)
        ->assertSee(Mutuality::Other->labelFor(AttestationKind::Camp))
        ->assertDontSee(Mutuality::MC->label())
        ->set('mutuality', Mutuality::Other->value)
        ->call('chooseMutuality')
        ->assertSet('step', 2)
        // Optional on the club's own camp attestation: an employer has no use for it.
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSet('step', 3)
        ->assertSee('STG-2627-00001');

    $attestation = MutualAttestation::sole();

    expect($attestation->kind)->toBe(AttestationKind::Camp)
        ->and($attestation->subscription_training_pack_id)->toBe($this->line->id);
});

it('asks Partenamut families about disability, and keeps nothing of the answer', function (): void {
    app(InstallAttestationTemplate::class)(
        Mutuality::Partenamut,
        base_path('database/seeders/Data/attestation-templates/camp/partenamut.pdf'),
        'partenamut.pdf',
        kind: AttestationKind::Camp,
    );

    Livewire::actingAs($this->member)
        ->test(CAMP_ATTESTATION_PAGE, ['user' => $this->member])
        ->call('chooseDocument', 'camp-' . $this->line->id)
        ->set('mutuality', Mutuality::Partenamut->value)
        ->call('chooseMutuality')
        ->assertSet('needsDisability', true)
        ->assertSet('needsMutualNumber', true)
        ->set('mutualMembershipNumber', '123-456')
        ->call('generate')
        ->assertHasErrors(['recognisedDisabled' => 'required'])
        ->set('recognisedDisabled', 'yes')
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSet('recognisedDisabled', null)
        ->assertSet('step', 3);
});

it('shows a camp not over yet with the day it opens', function (): void {
    Carbon::setTestNow('2026-10-23 10:00:00');

    Livewire::actingAs($this->member)
        ->test(CAMP_ATTESTATION_PAGE, ['user' => $this->member])
        ->assertSee('25/10/2026');
});

it('cannot reach another member\'s camp', function (): void {
    $other = campAttestationLine(campAttestationAffiliation($this->season), $this->camp);

    Livewire::actingAs($this->member)
        ->test(CAMP_ATTESTATION_PAGE, ['user' => $this->member])
        ->call('chooseDocument', 'camp-' . $other->id)
        ->assertNotFound();
});

it('reopens a camp attestation already issued', function (): void {
    $page = Livewire::actingAs($this->member)
        ->test(CAMP_ATTESTATION_PAGE, ['user' => $this->member])
        ->call('chooseDocument', 'camp-' . $this->line->id)
        ->set('mutuality', Mutuality::Other->value)
        ->call('chooseMutuality')
        ->call('generate');

    $page->call('backToDocuments')
        ->assertSee('STG-2627-00001')
        ->call('chooseDocument', 'camp-' . $this->line->id)
        ->assertSet('step', 3);
});
