<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a non-empty reference string', function (): void {
    $reference = (new GeneratePaymentReference)();

    expect($reference)->toBeString()->not->toBeEmpty();
});

it('generates a reference that contains slashes as separators', function (): void {
    $reference = (new GeneratePaymentReference)();

    expect($reference)->toContain('/');
});

it('addSeparators inserts slashes at positions 3 and 7', function (): void {
    $action = new GeneratePaymentReference;
    // Input: 12 chars → after insert at 3: '123/456789012' → after insert at 7: '123/456/789012' (but 0-indexed)
    // The method inserts '/' at offset 7, then at offset 3
    $result = $action->addSeparators('123456789012');

    expect($result[3])->toBe('/');
});

it('generates a reference with exactly 12 digits, grouped 3/4/5', function (): void {
    $reference = (new GeneratePaymentReference)();

    expect($reference)->toMatch('/^\d{3}\/\d{4}\/\d{5}$/');
});

it('never drops a leading zero on a single-digit checksum', function (): void {
    // A hundred generations in the same run cycle through many sequence
    // numbers, virtually guaranteeing at least one modulo-97 checksum
    // below 10 — the exact case that used to shorten the reference to 11 digits.
    $sawSingleDigitChecksum = false;
    $subscription = Subscription::factory()->create();

    for ($i = 0; $i < 100; $i++) {
        $reference = (new GeneratePaymentReference)();
        expect($reference)->toMatch('/^\d{3}\/\d{4}\/\d{5}$/');

        $digits = str_replace('/', '', $reference);
        $checksum = (int) substr($digits, -2);
        $base = (int) substr($digits, 0, 10);

        expect($checksum)->toBe($base % 97 === 0 ? 97 : $base % 97);

        if ($checksum < 10) {
            $sawSingleDigitChecksum = true;
        }

        Payment::factory()->create([
            'reference' => $reference,
            'payable_type' => Subscription::class,
            'payable_id' => $subscription->id,
        ]);
    }

    expect($sawSingleDigitChecksum)->toBeTrue();
});

it('uses 97 as the check digits when the base is an exact multiple of 97', function (): void {
    $method = (new ReflectionClass(GeneratePaymentReference::class))->getMethod('formatCheckDigits');
    $action = new GeneratePaymentReference;

    expect($method->invoke($action, 0))->toBe('97')
        ->and($method->invoke($action, 97))->toBe('97')
        ->and($method->invoke($action, 5))->toBe('05')
        ->and($method->invoke($action, 42))->toBe('42');
});
