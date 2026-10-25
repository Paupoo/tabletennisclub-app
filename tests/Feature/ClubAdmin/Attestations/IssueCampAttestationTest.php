<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\InstallAttestationTemplate;
use App\Actions\ClubAdmin\Attestations\IssueAttestation;
use App\Actions\ClubAdmin\Attestations\IssueCampAttestation;
use App\Actions\ClubAdmin\Subscriptions\AdjustTrainingCampLineAction;
use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\AnchorResolver;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\PdfTextExtractor;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Templates\AnchorMaps;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationKind;
use App\Domains\Shared\Enums\AttestationRefusal;
use App\Domains\Shared\Enums\Mutuality;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Subscriptions\Notifications\SubscriptionRefundRequestedNotification;
use App\Exceptions\AttestationNotAllowed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Mail::fake();
    Notification::fake();
    Carbon::setTestNow('2026-10-30 10:00:00');

    Club::factory()->ownClub()->create([
        'name' => 'C.T.T Ottignies-Blocry',
        'licence' => 'BBW214',
        'street' => "Rue de l'Invasion 80",
        'city_code' => '1340',
        'city_name' => 'Ottignies',
    ]);

    $secretary = User::factory()->create(['first_name' => 'Manon', 'last_name' => 'Patigny']);

    AttestationSetting::current()->update([
        'signatory_user_id' => $secretary->id,
        'seal_path' => base_path('database/seeders/Data/attestation-specimens/specimen-seal.png'),
        'signature_path' => base_path('database/seeders/Data/attestation-specimens/specimen-signature.png'),
    ]);

    $this->season = Season::factory()->create([
        'name' => '2026-2027',
        'is_active' => true,
        'start_at' => '2026-07-01',
        'end_at' => '2027-06-30',
    ]);

    $affiliation = campAttestationAffiliation($this->season);
    $affiliation->user->update(['first_name' => 'Léa', 'last_name' => 'Dupont', 'street' => 'Rue du Test 13', 'city_code' => '1348', 'city_name' => 'Louvain-la-Neuve']);
    $this->line = campAttestationLine($affiliation->refresh(), campAttestationCamp($this->season));
});

afterEach(fn () => Carbon::setTestNow());

function installCampForm(Mutuality $mutuality): void
{
    app(InstallAttestationTemplate::class)(
        $mutuality,
        base_path('database/seeders/Data/attestation-templates/camp/' . $mutuality->value . '.pdf'),
        $mutuality->value . '.pdf',
        kind: AttestationKind::Camp,
    );
}

it('finds every label its camp map names on the published form', function (Mutuality $mutuality): void {
    $layout = app(PdfTextExtractor::class)->extract(
        base_path('database/seeders/Data/attestation-templates/camp/' . $mutuality->value . '.pdf'),
    );

    expect(app(AnchorResolver::class)->unresolved($layout, AnchorMaps::for($mutuality, AttestationKind::Camp)))->toBe([]);
})->with(Mutuality::withCampForm());

it('writes the club attestation with the days, the dates and the sum', function (): void {
    $attestation = app(IssueCampAttestation::class)($this->line, Mutuality::Other);

    $text = campAttestationPdfText(Storage::disk('local')->path($attestation->path));

    expect($attestation->reference)->toBe('STG-2627-00001')
        ->and($attestation->kind)->toBe(AttestationKind::Camp)
        ->and($attestation->full_days)->toBe(5)
        ->and($attestation->half_days)->toBe(0)
        ->and($attestation->amount_certified)->toBe(80.0)
        ->and($attestation->period_from->toDateString())->toBe('2026-10-20')
        ->and($attestation->period_to->toDateString())->toBe('2026-10-24')
        ->and($text)->toContain('Léa Dupont')
        ->and($text)->toContain('5 jours')
        ->and($text)->toContain('20/10/2026')
        ->and($text)->toContain('24/10/2026')
        ->and($text)->toContain('80,00')
        ->and($text)->toContain('2026-2027')
        ->and($text)->toContain('Manon Patigny');
});

it('fills the Partenamut camp form, disability answer included', function (): void {
    installCampForm(Mutuality::Partenamut);

    $attestation = app(IssueCampAttestation::class)(
        $this->line,
        Mutuality::Partenamut,
        new MemberIdentifiers(mutualMembershipNumber: '123-456', recognisedDisabled: false),
    );

    $text = campAttestationPdfText(Storage::disk('local')->path($attestation->path));
    $squeezed = (string) preg_replace('/\s+/u', '', $text);

    expect($text)->toContain('Léa Dupont')
        ->and($text)->toContain('C.T.T Ottignies-Blocry')
        ->and($text)->toContain('123-456')
        // One digit per cell, read back between the form's own separators.
        ->and($squeezed)->toContain('Du20/10/2026au24/10/2026')
        ->and($squeezed)->toContain('80,00€')
        ->and($squeezed)->toContain('pour5jour(s)')
        ->and($squeezed)->toContain('handicapé(e):OUIXNON');
});

it('fills the Solidaris camp form and counts a half-day camp in half days', function (): void {
    installCampForm(Mutuality::Solidaris);

    $mornings = campAttestationCamp($this->season, [
        ['2026-10-26 09:00', '2026-10-26 12:00'],
        ['2026-10-27 09:00', '2026-10-27 12:00'],
        ['2026-10-28 09:00', '2026-10-28 12:00'],
    ]);
    $line = campAttestationLine($this->line->subscription, $mornings);

    $attestation = app(IssueCampAttestation::class)(
        $line,
        Mutuality::Solidaris,
        new MemberIdentifiers(nationalRegisterNumber: '12.03.04-123.45'),
    );

    $text = campAttestationPdfText(Storage::disk('local')->path($attestation->path));

    expect($attestation->half_days)->toBe(3)
        ->and($text)->toContain('Manon Patigny')
        ->and($text)->toContain('C.T.T Ottignies-Blocry')
        ->and($text)->toContain('soit 3 nuitées/jours/demi-jour')
        ->and($text)->toContain('80,00');
});

it('issues one camp attestation per line, and leaves the affiliation one free', function (): void {
    app(IssueCampAttestation::class)($this->line, Mutuality::Other);

    expect(fn () => app(IssueCampAttestation::class)($this->line, Mutuality::Other))
        ->toThrow(AttestationNotAllowed::class, AttestationRefusal::AlreadyIssued->message());

    $affiliation = app(IssueAttestation::class)($this->line->subscription->user, $this->season, Mutuality::Other);

    expect($affiliation->reference)->toBe('ATT-2627-00001')
        ->and($affiliation->kind)->toBe(AttestationKind::Affiliation);
});

it('refuses a camp that is not over', function (): void {
    Carbon::setTestNow('2026-10-22 10:00:00');

    expect(fn () => app(IssueCampAttestation::class)($this->line, Mutuality::Other))
        ->toThrow(AttestationNotAllowed::class);
});

it('warns the treasurer when a camp it certified is refunded in part', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->givePermissionTo(Permission::PaymentsRefund->value);

    $attestation = app(IssueCampAttestation::class)($this->line, Mutuality::Other);

    (new AdjustTrainingCampLineAction)($this->line->subscription, $this->line->trainingPack, 60.0, 'Two days missed');

    Notification::assertSentTo(
        $treasurer,
        SubscriptionRefundRequestedNotification::class,
        function (SubscriptionRefundRequestedNotification $notification) use ($attestation, $treasurer): bool {
            $mail = implode("\n", $notification->toMail($treasurer)->introLines);

            return $notification->attestation?->is($attestation)
                && str_contains($mail, 'STG-2627-00001');
        },
    );
});

it('says nothing of attestations on a camp refund with none', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->givePermissionTo(Permission::PaymentsRefund->value);

    (new AdjustTrainingCampLineAction)($this->line->subscription, $this->line->trainingPack, 60.0, 'Two days missed');

    Notification::assertSentTo(
        $treasurer,
        SubscriptionRefundRequestedNotification::class,
        fn (SubscriptionRefundRequestedNotification $notification): bool => $notification->attestation === null,
    );
});
