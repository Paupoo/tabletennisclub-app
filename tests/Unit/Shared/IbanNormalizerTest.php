<?php

declare(strict_types=1);

use App\Domains\Shared\Support\IbanNormalizer;

it('strips spaces from an IBAN', function (): void {
    expect(IbanNormalizer::normalize('BE68 5390 0754 7034'))->toBe('BE68539007547034');
});

it('uppercases a lowercase IBAN', function (): void {
    expect(IbanNormalizer::normalize('be68539007547034'))->toBe('BE68539007547034');
});

it('returns null for null input', function (): void {
    expect(IbanNormalizer::normalize(null))->toBeNull();
});

it('returns null for an empty or blank string', function (): void {
    expect(IbanNormalizer::normalize(''))->toBeNull()
        ->and(IbanNormalizer::normalize('   '))->toBeNull();
});

it('formats a compact IBAN into groups of 4 for display', function (): void {
    expect(IbanNormalizer::format('BE68539007547034'))->toBe('BE68 5390 0754 7034');
});

it('formats null as null', function (): void {
    expect(IbanNormalizer::format(null))->toBeNull();
});
