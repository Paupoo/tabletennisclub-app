<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\InstallAttestationTemplate;
use App\Actions\ClubAdmin\Attestations\PreviewAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Mutuality;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

/*
| The office had no way to see its seal on an insurer's page: it uploaded an
| image, set a width in millimetres and waited for a member to report a stamp
| printed over the amount. Three real defects survived that blind spot.
*/

function readPdfBytes(string $pdf): string
{
    $path = tempnam(sys_get_temp_dir(), 'preview') . '.pdf';
    file_put_contents($path, $pdf);

    $process = new Process(['pdftotext', '-layout', $path, '-']);
    $process->run();

    return (string) preg_replace('/\s+/u', ' ', $process->getOutput());
}

/**
 * The same text with every space removed.
 *
 * A rotated word is emitted as several positioned runs, so the watermark comes
 * back out of pdftotext as « SP ÉC IM EN ». It reads perfectly on the page;
 * only the extractor breaks it up.
 */
function readPdfSqueezed(string $pdf): string
{
    return (string) preg_replace('/\s+/u', '', readPdfBytes($pdf));
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

    $this->office = User::factory()->withRole(Role::ATTESTATIONS)->create([
        'first_name' => 'Manon',
        'last_name' => 'Patigny',
        'street' => 'Rue du Test 13',
        'city_code' => '1348',
        'city_name' => 'Louvain-la-Neuve',
    ]);
});

it('prints the viewer on the page, so the rehearsal is worth looking at', function (): void {
    $pdf = app(PreviewAttestation::class)($this->office, Mutuality::Other);

    expect(readPdfBytes($pdf))->toContain('Manon Patigny')
        ->toContain('C.T.T Ottignies-Blocry');
});

it('marks the page so it can never pass for a real attestation', function (): void {
    $pdf = app(PreviewAttestation::class)($this->office, Mutuality::Other);

    expect(readPdfSqueezed($pdf))->toContain('SPÉCIMEN');
});

it('works for somebody who has nothing to certify', function (): void {
    // The secretary checking the layout is rarely an affiliated, fully paid
    // member of the current season. Refusing them the rehearsal would defeat it.
    expect($this->office->subscriptions ?? collect())->toBeEmpty();

    $pdf = app(PreviewAttestation::class)($this->office, Mutuality::Other);

    expect(readPdfBytes($pdf))->toContain('Manon Patigny')
        ->toContain('2026-2027');
});

it('lays the rehearsal over the insurer form when one is held', function (): void {
    app(InstallAttestationTemplate::class)(
        Mutuality::Partenamut,
        base_path('database/seeders/Data/attestation-templates/partenamut.pdf'),
        'partenamut.pdf',
    );

    $text = readPdfBytes(app(PreviewAttestation::class)($this->office, Mutuality::Partenamut));

    expect($text)->toContain('Avantages Partenamut')
        ->toContain('Manon Patigny');

    expect(readPdfSqueezed(app(PreviewAttestation::class)($this->office, Mutuality::Partenamut)))
        ->toContain('SPÉCIMEN');
});

it('records nothing at all', function (): void {
    app(PreviewAttestation::class)($this->office, Mutuality::Other);

    expect(MutualAttestation::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('attestations'))->toBe([]);
});

it('hands the office a PDF from the screen', function (): void {
    Livewire::actingAs($this->office)
        ->test('pages::club-admin.attestations.index')
        ->call('preview', Mutuality::Other->value)
        ->assertHasNoErrors();
});

it('is closed to somebody without the délégation', function (): void {
    Livewire::actingAs(User::factory()->withRole(Role::MEMBERS)->create())
        ->test('pages::club-admin.attestations.index')
        ->call('preview', Mutuality::Other->value)
        ->assertForbidden();
});

/*
| The member's own block is the club's to fill too: it holds the name, the
| birth date, the address, the phone and the e-mail, and the member types only
| the national register number. Leaving those blank sent people back to a pen
| for facts the club already had. Only the signature stays theirs.
*/
it('fills the member block the club already holds, on every form that has one', function (Mutuality $mutuality, array $expected): void {
    app(InstallAttestationTemplate::class)(
        $mutuality,
        base_path('database/seeders/Data/attestation-templates/' . $mutuality->value . '.pdf'),
        $mutuality->value . '.pdf',
    );

    $member = User::factory()->create([
        'first_name' => 'Aurélien',
        'last_name' => 'Paulus',
        'birthdate' => '1988-08-17',
        'street' => 'Rue de la Chapelle 30',
        'city_code' => '1340',
        'city_name' => 'Ottignies',
        'phone_number' => '0479577502',
        'email' => 'membre@example.org',
    ]);
    $member->assignRole(Role::ATTESTATIONS->value);

    $text = readPdfBytes(app(PreviewAttestation::class)($member, $mutuality));

    foreach ($expected as $fact) {
        expect($text)->toContain($fact);
    }
})->with([
    'MC' => [Mutuality::MC, ['Aurélien Paulus', '17', '08', '1988', 'Rue de la Chapelle 30', '1340 Ottignies', '0479577502', 'membre@example.org']],
    'Partenamut' => [Mutuality::Partenamut, ['Paulus', 'Aurélien', 'Rue de la Chapelle 30', '1340 Ottignies']],
    'Solidaris' => [Mutuality::Solidaris, ['Aurélien Paulus', 'Ottignies']],
    'MutPlus' => [Mutuality::MutPlus, ['Aurélien Paulus', 'Rue de la Chapelle 30', 'membre@example.org']],
    'Neutre' => [Mutuality::Neutral, ['Paulus', 'Aurélien', 'Rue de la Chapelle 30', 'membre@example.org']],
]);

it('leaves the member their own signature to write', function (): void {
    // Everything the club can state is printed; the signature never is.
    app(InstallAttestationTemplate::class)(
        Mutuality::MC,
        base_path('database/seeders/Data/attestation-templates/mc.pdf'),
        'mc.pdf',
    );

    $text = readPdfBytes(app(PreviewAttestation::class)($this->office, Mutuality::MC));

    expect($text)->toContain('Signature');
});
