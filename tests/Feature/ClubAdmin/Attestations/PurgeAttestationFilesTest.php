<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function heldAttestation(string $issuedAt, string $file): MutualAttestation
{
    Storage::disk('local')->put($file, 'pdf');

    return MutualAttestation::factory()->create(['issued_at' => $issuedAt, 'path' => $file]);
}

it('deletes a file the club has held for more than a year, and keeps its record', function (): void {
    $old = heldAttestation(now()->subMonths(13)->toDateTimeString(), 'attestations/1/old.pdf');

    $this->artisan('attestations:purge')->assertSuccessful();

    $old->refresh();

    expect(Storage::disk('local')->exists('attestations/1/old.pdf'))->toBeFalse()
        ->and($old->path)->toBeNull()
        ->and($old->purged_at)->not->toBeNull()
        ->and($old->exists)->toBeTrue()
        ->and($old->reference)->not->toBeEmpty();
});

it('leaves this season alone', function (): void {
    $recent = heldAttestation(now()->subMonths(3)->toDateTimeString(), 'attestations/1/recent.pdf');

    $this->artisan('attestations:purge')->assertSuccessful();

    expect(Storage::disk('local')->exists('attestations/1/recent.pdf'))->toBeTrue()
        ->and($recent->fresh()->path)->toBe('attestations/1/recent.pdf');
});

it('keeps the verification answering after the file is gone', function (): void {
    $old = heldAttestation(now()->subMonths(18)->toDateTimeString(), 'attestations/1/gone.pdf');

    $this->artisan('attestations:purge')->assertSuccessful();

    $this->get(route('attestations.verify', ['token' => $old->token]))
        ->assertOk()
        ->assertSee($old->reference);
});
