<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Users\Models\User;

/*
| Public on purpose: the person checking works at an insurer's desk and has no
| account here. The token is printed on the document, so reaching this page
| means already holding every fact it repeats — what it adds is whether the
| certificate still stands.
*/

it('confirms a certificate the club still stands behind', function (): void {
    $member = User::factory()->create(['first_name' => 'Marc', 'last_name' => 'Dupont']);
    $attestation = MutualAttestation::factory()->create([
        'user_id' => $member->id,
        'signatory_name' => 'Manon Patigny',
        'amount_certified' => 205,
    ]);

    $this->get(route('attestations.verify', ['token' => $attestation->token]))
        ->assertOk()
        ->assertSee('Marc Dupont')
        ->assertSee($attestation->reference)
        ->assertSee('Manon Patigny')
        ->assertSee('205,00');
});

it('says so when a certificate has been withdrawn', function (): void {
    $attestation = MutualAttestation::factory()->revoked()->create();

    $this->get(route('attestations.verify', ['token' => $attestation->token]))
        ->assertOk()
        ->assertSee(__('This attestation has been revoked and is no longer valid.'));
});

it('answers nothing at all to a token it does not know', function (): void {
    $this->get(route('attestations.verify', ['token' => 'not-a-real-token']))->assertNotFound();
});

it('repeats what the document says and nothing more', function (): void {
    $member = User::factory()->create([
        'email' => 'marc.dupont@example.org',
        'street' => 'Rue du Test 13',
        'phone_number' => '0475123456',
    ]);
    $attestation = MutualAttestation::factory()->create(['user_id' => $member->id]);

    // The national register number was never stored, and the contact details
    // have no business being republished to whoever scans the code.
    $this->get(route('attestations.verify', ['token' => $attestation->token]))
        ->assertOk()
        ->assertDontSee('marc.dupont@example.org')
        ->assertDontSee('Rue du Test 13')
        ->assertDontSee('0475123456');
});
