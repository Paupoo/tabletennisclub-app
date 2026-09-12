<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationAvailability;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Mutuality;

function readyToStamp(): void
{
    AttestationSetting::current()->update([
        'signatory_user_id' => User::factory()->create()->id,
        'seal_path' => base_path('database/seeders/Data/attestation-specimens/specimen-seal.png'),
        'signature_path' => base_path('database/seeders/Data/attestation-specimens/specimen-signature.png'),
    ]);
}

function holdTemplate(Mutuality $mutuality, array $unresolved = []): AttestationTemplate
{
    return AttestationTemplate::create([
        'mutuality' => $mutuality->value,
        'path' => 'attestation-templates/' . $mutuality->value . '.pdf',
        'original_name' => $mutuality->value . '.pdf',
        'page_count' => 1,
        'unresolved_fields' => $unresolved,
    ]);
}

it('stays shut until the club has provided a signatory, a seal and a signature', function (): void {
    expect(app(AttestationAvailability::class)->isReady())->toBeFalse()
        ->and(app(AttestationAvailability::class)->missing())->toHaveCount(3);

    readyToStamp();

    expect(app(AttestationAvailability::class)->isReady())->toBeTrue();
});

it('offers the two insurers that accept a club-written attestation, with no form at all', function (): void {
    readyToStamp();

    $offered = app(AttestationAvailability::class)->offered();

    expect($offered)->toContain(Mutuality::MC)
        ->toContain(Mutuality::Neutral)
        ->toContain(Mutuality::Other)
        ->not->toContain(Mutuality::Partenamut)
        ->not->toContain(Mutuality::Solidaris)
        ->not->toContain(Mutuality::MutPlus);
});

it('offers an insurer as soon as its form is held and usable', function (): void {
    readyToStamp();
    holdTemplate(Mutuality::Partenamut);

    expect(app(AttestationAvailability::class)->offered())->toContain(Mutuality::Partenamut);
});

it('withdraws an insurer whose form has lost a label', function (): void {
    readyToStamp();
    holdTemplate(Mutuality::Solidaris, ['amount']);

    expect(app(AttestationAvailability::class)->offered())->not->toContain(Mutuality::Solidaris);
});

it('offers nothing at all while the club is not ready', function (): void {
    holdTemplate(Mutuality::Partenamut);

    expect(app(AttestationAvailability::class)->offered())->toBe([]);
});
