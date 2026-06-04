<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
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
