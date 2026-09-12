<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\RevokeAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Storage;

it('withdraws an attestation and keeps the reason with it', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('attestations/1/ATT-2627-00001.pdf', 'pdf');

    $attestation = MutualAttestation::factory()->create(['path' => 'attestations/1/ATT-2627-00001.pdf']);
    $secretary = User::factory()->create();

    app(RevokeAttestation::class)($attestation, 'Mutuelle erronée', $secretary);

    $attestation->refresh();

    expect($attestation->isRevoked())->toBeTrue()
        ->and($attestation->revocation_reason)->toBe('Mutuelle erronée')
        ->and($attestation->path)->toBeNull()
        ->and(Storage::disk('local')->exists('attestations/1/ATT-2627-00001.pdf'))->toBeFalse();
});

it('refuses to withdraw without a reason', function (): void {
    $attestation = MutualAttestation::factory()->create();

    expect(fn () => app(RevokeAttestation::class)($attestation, '   ', User::factory()->create()))
        ->toThrow(InvalidArgumentException::class);

    expect($attestation->fresh()->isRevoked())->toBeFalse();
});

it('leaves an attestation already withdrawn alone', function (): void {
    $attestation = MutualAttestation::factory()->revoked('Première raison')->create();

    app(RevokeAttestation::class)($attestation, 'Deuxième raison', User::factory()->create());

    expect($attestation->fresh()->revocation_reason)->toBe('Première raison');
});
