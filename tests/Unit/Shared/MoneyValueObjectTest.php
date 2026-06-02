<?php

declare(strict_types=1);

use App\Domains\Shared\ValueObjects\Money;

test('money can be created from cents', function () {
    $money = Money::fromCents(1000);
    expect($money->cents())->toBe(1000);
});

test('money can be created from euros', function () {
    $money = Money::fromEuros(10.50);
    expect($money->cents())->toBe(1050);
});

test('money converts euros correctly', function () {
    $money = Money::fromCents(2550);
    expect($money->euros())->toBe(25.50);
});

test('money rejects negative amounts', function () {
    expect(fn () => Money::fromCents(-100))
        ->toThrow(InvalidArgumentException::class);
});

test('money can add amounts', function () {
    $money1 = Money::fromEuros(10.00);
    $money2 = Money::fromEuros(5.50);
    $result = $money1->add($money2);

    expect($result->euros())->toBe(15.50);
});

test('money can subtract amounts', function () {
    $money1 = Money::fromEuros(10.00);
    $money2 = Money::fromEuros(3.00);
    $result = $money1->subtract($money2);

    expect($result->euros())->toBe(7.00);
});

test('money can multiply', function () {
    $money = Money::fromEuros(10.00);
    $result = $money->multiply(3.5);

    expect($result->euros())->toBe(35.00);
});

test('money can be compared for equality', function () {
    $money1 = Money::fromEuros(10.00);
    $money2 = Money::fromEuros(10.00);
    $money3 = Money::fromEuros(5.00);

    expect($money1->equals($money2))->toBeTrue();
    expect($money1->equals($money3))->toBeFalse();
});

test('money can be compared for greater than', function () {
    $money1 = Money::fromEuros(10.00);
    $money2 = Money::fromEuros(5.00);

    expect($money1->isGreaterThan($money2))->toBeTrue();
    expect($money2->isGreaterThan($money1))->toBeFalse();
});

test('money can be compared for less than', function () {
    $money1 = Money::fromEuros(5.00);
    $money2 = Money::fromEuros(10.00);

    expect($money1->isLessThan($money2))->toBeTrue();
    expect($money2->isLessThan($money1))->toBeFalse();
});

test('money can check if zero', function () {
    $zero = Money::fromCents(0);
    $nonZero = Money::fromEuros(1.00);

    expect($zero->isZero())->toBeTrue();
    expect($nonZero->isZero())->toBeFalse();
});

test('money converts to string', function () {
    $money = Money::fromEuros(42.50);
    expect((string) $money)->toContain('42,50');
});

test('money handles zero cents correctly', function () {
    $money = Money::fromEuros(0.00);
    expect($money->cents())->toBe(0);
    expect($money->euros())->toBe(0.00);
});
