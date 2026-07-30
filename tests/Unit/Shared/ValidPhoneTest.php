<?php

declare(strict_types=1);

use App\Domains\Shared\Rules\ValidPhone;

it('accepts a Belgian mobile number', function (string $phone): void {
    expect(ValidPhone::check($phone))->toBeTrue();
})->with([
    '0475 12 34 56',
    '0475123456',
    '0475.12.34.56',
    '0475-12-34-56',
    '0475/12.34.56',
    '+32 475 12 34 56',
    '+32475123456',
    '0032 475 12 34 56',
]);

it('accepts a Belgian landline number', function (): void {
    expect(ValidPhone::check('010 45 67 89'))->toBeTrue();
});

it('accepts a foreign number in international notation', function (): void {
    expect(ValidPhone::check('+33 6 12 34 56 78'))->toBeTrue();
});

it('rejects a value that is not a phone number at all', function (string $phone): void {
    expect(ValidPhone::check($phone))->toBeFalse();
})->with([
    'azerty',
    '04 75 AZ 34 56',
    'appelez-moi',
    '',
    '   ',
]);

it('rejects a number that is too short to be dialled', function (): void {
    expect(ValidPhone::check('0475 12'))->toBeFalse();
});

it('rejects a number longer than the E.164 ceiling', function (): void {
    expect(ValidPhone::check('+33 6 12 34 56 78 90 12 34'))->toBeFalse();
});

it('normalizes the international prefix back to the national notation', function (): void {
    expect(ValidPhone::normalize('+32 475 12 34 56'))->toBe('0475123456')
        ->and(ValidPhone::normalize('0032475123456'))->toBe('0475123456')
        ->and(ValidPhone::normalize('0475 12 34 56'))->toBe('0475123456');
});

it('keeps a foreign number in international notation when normalizing', function (): void {
    expect(ValidPhone::normalize('0033 6 12 34 56 78'))->toBe('+33612345678');
});

it('normalizes a null or blank value to null', function (): void {
    expect(ValidPhone::normalize(null))->toBeNull()
        ->and(ValidPhone::normalize('  '))->toBeNull();
});

it('calls the fail closure when the phone number is invalid', function (): void {
    $failed = false;
    $rule = new ValidPhone;
    $rule->validate('guardianPhone', 'azerty', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('does not call the fail closure when the phone number is valid', function (): void {
    $failed = false;
    $rule = new ValidPhone;
    $rule->validate('guardianPhone', '0475 12 34 56', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('calls the fail closure when the value is not a string', function (): void {
    $failed = false;
    $rule = new ValidPhone;
    $rule->validate('guardianPhone', 475123456, function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});
