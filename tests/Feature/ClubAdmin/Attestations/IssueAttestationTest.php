<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\InstallAttestationTemplate;
use App\Actions\ClubAdmin\Attestations\IssueAttestation;
use App\Actions\ClubAdmin\Attestations\RevokeAttestation;
use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\OfficialFormRenderer;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationFieldValues;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\BuildAttestationData;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Templates\AnchorMaps;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationRefusal;
use App\Domains\Shared\Enums\Mutuality;
use App\Exceptions\AttestationNotAllowed;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/*
| The six documents the club can hand out, read back as text. A PDF that opens
| and is empty is a valid PDF: only pulling the words out of it proves that the
| member's name, the amount and the period actually reached the paper.
*/

function specimen(string $name): string
{
    return base_path('database/seeders/Data/attestation-specimens/' . $name . '.png');
}

function readPdf(string $path): string
{
    $process = new Process(['pdftotext', '-layout', $path, '-']);
    $process->run();

    return stripFillRules($process->getOutput());
}

/**
 * Strips the fill rules a form draws with characters.
 *
 * poppler decides how to interleave a drawn value with the dotted rule it sits
 * on, and that decision differs between versions: « Marc Dupont » comes back
 * whole on one machine and as « Marc ......... Dupont » on another. The rules
 * are the form's furniture, not its content, so they go before anything is
 * compared — otherwise these tests pass or fail on the runner's poppler.
 */
function stripFillRules(string $text): string
{
    return (string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[._\x{2026}]{2,}/u', ' ', $text));
}

function certifiableMember(Season $season): Subscription
{
    $affiliation = Subscription::factory()->for(User::factory()->create([
        'first_name' => 'Marc',
        'last_name' => 'Dupont',
        'street' => 'Rue du Test 13',
        'city_code' => '1348',
        'city_name' => 'Louvain-la-Neuve',
    ]))->create([
        'season_id' => $season->id,
        'status' => 'paid',
        'subscription_price' => 125,
        'amount_due' => 205,
    ]);

    $affiliation->forceFill(['confirmed_at' => '2026-09-14 18:30:00'])->save();

    Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $affiliation->id,
        'amount_due' => 205,
        'amount_paid' => 205,
        'status' => 'paid',
        'payment_method' => 'transfer',
    ]);

    return $affiliation->refresh();
}

beforeEach(function (): void {
    Storage::fake('local');

    Club::factory()->ownClub()->create([
        'name' => 'C.T.T Ottignies-Blocry',
        'licence' => 'BBW214',
        'street' => "Rue de l'Invasion 80",
        'city_code' => '1340',
        'city_name' => 'Ottignies',
        'phone_contact' => '010 45 12 34',
    ]);

    $secretary = User::factory()->create(['first_name' => 'Manon', 'last_name' => 'Patigny']);

    AttestationSetting::current()->update([
        'signatory_user_id' => $secretary->id,
        'seal_path' => specimen('specimen-seal'),
        'signature_path' => specimen('specimen-signature'),
    ]);

    $this->season = Season::factory()->create([
        'name' => '2026-2027',
        'is_active' => true,
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
    ]);
});

it('prints every field its map declares, on every insurer form', function (Mutuality $mutuality): void {
    $affiliation = certifiableMember($this->season);

    app(InstallAttestationTemplate::class)(
        $mutuality,
        base_path('database/seeders/Data/attestation-templates/' . $mutuality->value . '.pdf'),
        $mutuality->value . '.pdf',
    );

    $attestation = app(IssueAttestation::class)(
        $affiliation->user,
        $this->season,
        $mutuality,
        new MemberIdentifiers(nationalRegisterNumber: '90.06.05-123.45'),
    );

    // Whitespace collapsed: the layout puts a value in a box that lines up with
    // other text, and the reader pads between them.
    $text = readPdf(Storage::disk('local')->path($attestation->path));

    $values = app(AttestationFieldValues::class)->for(
        app(BuildAttestationData::class)->for($affiliation),
        $mutuality,
        new MemberIdentifiers(nationalRegisterNumber: '90.06.05-123.45'),
        $attestation->reference,
    );

    $missing = [];

    foreach (array_keys(AnchorMaps::for($mutuality)) as $field) {
        // The seal and the signature are images; nothing to read back.
        if (in_array($field, ['seal', 'signature'], true)) {
            continue;
        }

        $expected = trim($values[$field] ?? '');

        if ($expected !== '' && ! str_contains($text, $expected)) {
            $missing[] = $field . ' (' . $expected . ')';
        }
    }

    expect($missing)->toBe([]);
})->with(Mutuality::withOfficialForm());

it('names the member, the sum and the day it starts on every form', function (Mutuality $mutuality): void {
    $affiliation = certifiableMember($this->season);

    app(InstallAttestationTemplate::class)(
        $mutuality,
        base_path('database/seeders/Data/attestation-templates/' . $mutuality->value . '.pdf'),
        $mutuality->value . '.pdf',
    );

    $attestation = app(IssueAttestation::class)($affiliation->user, $this->season, $mutuality);
    $text = readPdf(Storage::disk('local')->path($attestation->path));

    // Squeezed as well: Partenamut draws the amount and the date as
    // single-character cells, so neither survives as a contiguous string.
    $squeezed = (string) preg_replace('/\s+/u', '', $text);

    expect($text)->toContain('Marc Dupont')
        ->and($text)->toContain('Tennis de table')
        ->and($squeezed)->toContain('205')
        ->and($squeezed)->toContain('2026');
})->with(Mutuality::withOfficialForm());

it('writes its own certificate for an insurer it holds no form for', function (): void {
    $affiliation = certifiableMember($this->season);

    $attestation = app(IssueAttestation::class)(
        $affiliation->user,
        $this->season,
        Mutuality::Other,
        new MemberIdentifiers(nationalRegisterNumber: '90.06.05-123.45'),
    );

    $text = readPdf(Storage::disk('local')->path($attestation->path));

    expect($text)->toContain('Marc Dupont')
        ->and($text)->toContain('C.T.T Ottignies-Blocry')
        ->and($text)->toContain('Manon Patigny')
        ->and($text)->toContain('205,00')
        ->and($text)->toContain('Tennis de table')
        ->and($text)->toContain('90.06.05-123.45')
        ->and($text)->toContain($attestation->reference);
});

it('copies onto the record exactly what the paper says', function (): void {
    $affiliation = certifiableMember($this->season);

    $attestation = app(IssueAttestation::class)($affiliation->user, $this->season, Mutuality::Other);

    expect($attestation->amount_certified)->toBe(205.0)
        ->and($attestation->period_from->toDateString())->toBe('2026-09-14')
        ->and($attestation->period_to->toDateString())->toBe('2027-06-30')
        ->and($attestation->signatory_name)->toBe('Manon Patigny')
        ->and($attestation->discipline)->toBe('Tennis de table')
        ->and($attestation->reference)->toBe('ATT-2627-00001');
});

it('refuses a second attestation for the same season', function (): void {
    $affiliation = certifiableMember($this->season);

    app(IssueAttestation::class)($affiliation->user, $this->season, Mutuality::Other);

    expect(fn () => app(IssueAttestation::class)($affiliation->user, $this->season, Mutuality::MC))
        ->toThrow(AttestationNotAllowed::class);

    expect(MutualAttestation::count())->toBe(1);
});

it('refuses a member whose cotisation is not settled', function (): void {
    $affiliation = Subscription::factory()->for(User::factory())->create([
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'amount_due' => 125,
    ]);

    try {
        app(IssueAttestation::class)($affiliation->user, $this->season, Mutuality::Other);
        $this->fail('An unpaid affiliation was certified.');
    } catch (AttestationNotAllowed $refused) {
        expect($refused->refusal)->toBe(AttestationRefusal::BalanceDue);
    }
});

it('falls back to its own certificate when a form has lost a label', function (): void {
    $affiliation = certifiableMember($this->season);

    $template = app(InstallAttestationTemplate::class)(
        Mutuality::MC,
        base_path('database/seeders/Data/attestation-templates/mc.pdf'),
        'mc.pdf',
    );
    $template->update(['unresolved_fields' => ['amount']]);

    $attestation = app(IssueAttestation::class)($affiliation->user, $this->season, Mutuality::MC);

    // The club's own wording, which the MC form does not carry.
    expect(readPdf(Storage::disk('local')->path($attestation->path)))
        ->toContain('C.T.T Ottignies-Blocry')
        ->toContain($attestation->reference);
});

it('lets the office issue again once the first one is withdrawn', function (): void {
    $affiliation = certifiableMember($this->season);
    $secretary = User::factory()->create();

    $first = app(IssueAttestation::class)($affiliation->user, $this->season, Mutuality::Other);
    app(RevokeAttestation::class)($first, 'Mutuelle erronée', $secretary);

    $second = app(IssueAttestation::class)($affiliation->user, $this->season, Mutuality::Other, issuedBy: $secretary);

    expect($second->id)->not->toBe($first->id)
        ->and($second->reference)->toBe('ATT-2627-00002')
        ->and($second->issued_by_user_id)->toBe($secretary->id)
        ->and($first->fresh()->isRevoked())->toBeTrue();
});

it('certifies another member of the same season without complaint', function (): void {
    $one = certifiableMember($this->season);
    $two = certifiableMember($this->season);

    app(IssueAttestation::class)($one->user, $this->season, Mutuality::Other);
    $second = app(IssueAttestation::class)($two->user, $this->season, Mutuality::Other);

    expect(MutualAttestation::count())->toBe(2)
        ->and($second->user_id)->toBe($two->user_id);
});

/*
| mPDF embeds a subset of the font, built from the characters it has seen. The
| only call that positions on a baseline — which an overlay needs — is also the
| only one that never registers what it draws, so every accent came out as an
| empty box: « Aurélien » printed as « Aur□lien » on a document the club signs.
|
| pdftotext reads the accent back correctly either way, because the text layer
| was never wrong — the glyph was missing. What does move is the embedded font:
| the é adds a few hundred bytes of outline. A render that gains nothing over
| its unaccented twin is a render whose accents are boxes.
*/
it('embeds the glyphs for the accents it prints, not empty boxes', function (): void {
    app(InstallAttestationTemplate::class)(
        Mutuality::MC,
        base_path('database/seeders/Data/attestation-templates/mc.pdf'),
        'mc.pdf',
    );

    $renderer = app(OfficialFormRenderer::class);
    $template = Storage::disk('local')->path('attestation-templates/mc.pdf');
    $settings = AttestationSetting::current();
    $common = ['club_name' => 'Club', 'discipline' => 'Tennis', 'amount_euros' => '125'];

    $plain = $renderer->render($template, Mutuality::MC, $common + ['member_full_name' => 'Aurelien Paulus'], $settings);
    $accented = $renderer->render($template, Mutuality::MC, $common + ['member_full_name' => 'Aurélien Paulus'], $settings);

    expect(strlen($accented) - strlen($plain))->toBeGreaterThan(100);
});
