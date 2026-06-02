<?php

declare(strict_types=1);

use App\Domains\Shared\Rules\ValidIban;

it('validates a correct Belgian IBAN', function (): void {
    expect(ValidIban::check('BE68539007547034'))->toBeTrue();
});

it('rejects an IBAN with wrong checksum', function (): void {
    expect(ValidIban::check('BE00539007547034'))->toBeFalse();
});

it('rejects an IBAN that is too short', function (): void {
    expect(ValidIban::check('BE685390'))->toBeFalse();
});

it('rejects an empty string', function (): void {
    expect(ValidIban::check(''))->toBeFalse();
});

it('accepts IBAN with spaces (strips them)', function (): void {
    expect(ValidIban::check('BE68 5390 0754 7034'))->toBeTrue();
});

it('calls the fail closure when IBAN is invalid', function (): void {
    $failed = false;
    $rule = new ValidIban;
    $rule->validate('iban', 'INVALID', function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('does not call the fail closure when IBAN is valid', function (): void {
    $failed = false;
    $rule = new ValidIban;
    $rule->validate('iban', 'BE68539007547034', function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

it('calls the fail closure when value is not a string', function (): void {
    $failed = false;
    $rule = new ValidIban;
    $rule->validate('iban', 12345, function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});
