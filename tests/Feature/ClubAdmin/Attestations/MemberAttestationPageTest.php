<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\InstallAttestationTemplate;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationRefusal;
use App\Domains\Shared\Enums\Mutuality;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const ATTESTATION_PAGE = 'pages::club-admin.users.user-space.mutual-attestation';

function certifiable(Season $season): Subscription
{
    $affiliation = Subscription::factory()->for(User::factory())->create([
        'season_id' => $season->id,
        'status' => 'paid',
        'subscription_price' => 125,
        'amount_due' => 125,
    ]);

    $affiliation->forceFill(['confirmed_at' => '2026-09-14 18:30:00'])->save();

    Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $affiliation->id,
        'amount_due' => 125,
        'amount_paid' => 125,
        'status' => 'paid',
    ]);

    return $affiliation->refresh();
}

beforeEach(function (): void {
    Storage::fake('local');

    Club::factory()->ownClub()->create([
        'name' => 'C.T.T Ottignies-Blocry',
        'street' => "Rue de l'Invasion 80",
        'city_code' => '1340',
        'city_name' => 'Ottignies',
        'phone_contact' => '010 45 12 34',
    ]);

    AttestationSetting::current()->update([
        'signatory_user_id' => User::factory()->create(['first_name' => 'Manon', 'last_name' => 'Patigny'])->id,
        'seal_path' => base_path('database/seeders/Data/attestation-specimens/specimen-seal.png'),
        'signature_path' => base_path('database/seeders/Data/attestation-specimens/specimen-signature.png'),
    ]);

    $this->season = Season::factory()->create([
        'name' => '2026-2027',
        'is_active' => true,
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
    ]);
});

it('walks a member from choosing an insurer to a downloadable document', function (): void {
    $member = certifiable($this->season)->user;

    Livewire::actingAs($member)
        ->test(ATTESTATION_PAGE, ['user' => $member])
        ->assertSet('step', 1)
        ->set('mutuality', Mutuality::Other->value)
        ->call('chooseMutuality')
        ->assertSet('step', 2)
        ->set('nationalRegisterNumber', '90.06.05-123.45')
        ->call('generate')
        ->assertSet('step', 3)
        ->assertHasNoErrors();

    $attestation = MutualAttestation::where('user_id', $member->id)->firstOrFail();

    expect($attestation->reference)->toBe('ATT-2627-00001')
        ->and(Storage::disk('local')->exists($attestation->path))->toBeTrue();
});

it('drops the national register number the moment it reaches the paper', function (): void {
    $member = certifiable($this->season)->user;

    Livewire::actingAs($member)
        ->test(ATTESTATION_PAGE, ['user' => $member])
        ->set('mutuality', Mutuality::Other->value)
        ->call('chooseMutuality')
        ->set('nationalRegisterNumber', '90.06.05-123.45')
        ->call('generate')
        ->assertSet('nationalRegisterNumber', null);

    expect(User::find($member->id)->toArray())->not->toContain('90.06.05-123.45');
});

it('will not generate without a national register number', function (): void {
    $member = certifiable($this->season)->user;

    Livewire::actingAs($member)
        ->test(ATTESTATION_PAGE, ['user' => $member])
        ->set('mutuality', Mutuality::Other->value)
        ->call('chooseMutuality')
        ->call('generate')
        ->assertHasErrors(['nationalRegisterNumber'])
        ->assertSet('step', 2);

    expect(MutualAttestation::count())->toBe(0);
});

it('tells an unpaid member why, and offers nothing else', function (): void {
    $affiliation = Subscription::factory()->for(User::factory())->create([
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'amount_due' => 125,
    ]);

    Livewire::actingAs($affiliation->user)
        ->test(ATTESTATION_PAGE, ['user' => $affiliation->user])
        ->assertSee(AttestationRefusal::BalanceDue->message())
        ->assertDontSee(__('Generate my attestation'));
});

it('opens on the document a member already holds', function (): void {
    $member = certifiable($this->season)->user;
    $held = MutualAttestation::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'reference' => 'ATT-2627-00007',
    ]);

    Livewire::actingAs($member)
        ->test(ATTESTATION_PAGE, ['user' => $member])
        ->assertSet('step', 3)
        ->assertSee('ATT-2627-00007');
});

it('only offers the insurers the club can actually serve', function (): void {
    $member = certifiable($this->season)->user;

    Livewire::actingAs($member)
        ->test(ATTESTATION_PAGE, ['user' => $member])
        ->assertSee(Mutuality::MC->label())
        ->assertDontSee(Mutuality::Partenamut->label());
});

it('keeps a member out of somebody else\'s space', function (): void {
    $mine = certifiable($this->season)->user;
    $theirs = certifiable($this->season)->user;

    Livewire::actingAs($mine)
        ->test(ATTESTATION_PAGE, ['user' => $theirs])
        ->assertForbidden();
});

it('hands a member their own document back, as often as they ask', function (): void {
    $member = certifiable($this->season)->user;
    Storage::disk('local')->put('attestations/1/ATT-2627-00001.pdf', '%PDF-1.4');

    $held = MutualAttestation::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'reference' => 'ATT-2627-00001',
        'path' => 'attestations/1/ATT-2627-00001.pdf',
    ]);

    $this->actingAs($member)
        ->get(route('admin.user.attestation.download', $held))
        ->assertOk()
        ->assertDownload('ATT-2627-00001.pdf');
});

it('refuses to hand over somebody else\'s document', function (): void {
    Storage::disk('local')->put('attestations/1/x.pdf', '%PDF-1.4');
    $held = MutualAttestation::factory()->create(['path' => 'attestations/1/x.pdf']);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.user.attestation.download', $held))
        ->assertForbidden();
});

it('answers not-found once the file has been purged', function (): void {
    $member = certifiable($this->season)->user;
    $held = MutualAttestation::factory()->create(['user_id' => $member->id, 'path' => null]);

    $this->actingAs($member)
        ->get(route('admin.user.attestation.download', $held))
        ->assertNotFound();
});

/*
| Only Partenamut has a box for the mutual membership number, and only
| Partenamut has none for the national register number. Asking for both every
| time sent members hunting for a number that would never be printed; marking
| either optional sent the one form that needs it out blank.
*/
function holdForm(Mutuality $mutuality): void
{
    app(InstallAttestationTemplate::class)(
        $mutuality,
        base_path('database/seeders/Data/attestation-templates/' . $mutuality->value . '.pdf'),
        $mutuality->value . '.pdf',
    );
}

it('asks only for the numbers the chosen document will print', function (Mutuality $mutuality, bool $nrn, bool $mutual): void {
    if ($mutuality !== Mutuality::Other) {
        holdForm($mutuality);
    }

    $member = certifiable($this->season)->user;

    Livewire::actingAs($member)
        ->test(ATTESTATION_PAGE, ['user' => $member])
        ->set('mutuality', $mutuality->value)
        ->call('chooseMutuality')
        ->assertSet('needsNationalRegisterNumber', $nrn)
        ->assertSet('needsMutualNumber', $mutual);
})->with([
    'MC' => [Mutuality::MC, true, false],
    'Partenamut' => [Mutuality::Partenamut, false, true],
    'Solidaris' => [Mutuality::Solidaris, true, false],
    'club attestation' => [Mutuality::Other, true, false],
]);

it('requires the mutual number on the one form that prints it', function (): void {
    holdForm(Mutuality::Partenamut);
    $member = certifiable($this->season)->user;

    Livewire::actingAs($member)
        ->test(ATTESTATION_PAGE, ['user' => $member])
        ->set('mutuality', Mutuality::Partenamut->value)
        ->call('chooseMutuality')
        ->call('generate')
        ->assertHasErrors(['mutualMembershipNumber'])
        ->assertHasNoErrors(['nationalRegisterNumber']);

    expect(MutualAttestation::count())->toBe(0);
});

it('forgets a number typed for a mutual insurer the member then changed', function (): void {
    holdForm(Mutuality::Partenamut);
    $member = certifiable($this->season)->user;

    Livewire::actingAs($member)
        ->test(ATTESTATION_PAGE, ['user' => $member])
        ->set('mutuality', Mutuality::Partenamut->value)
        ->call('chooseMutuality')
        ->set('mutualMembershipNumber', 'P-4471902')
        ->call('back')
        ->set('mutuality', Mutuality::Other->value)
        ->call('chooseMutuality')
        ->assertSet('mutualMembershipNumber', null);
});
