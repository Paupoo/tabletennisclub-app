<?php

declare(strict_types=1);

use App\Domains\Shared\ValueObjects\Price;

test('price can be created from cents', function () {
    $price = Price::fromCents(2000);
    expect($price->cents())->toBe(2000);
});

test('price can be created from euros', function () {
    $price = Price::fromEuros(20.00);
    expect($price->cents())->toBe(2000);
});

test('price can be created as free', function () {
    $price = Price::free();
    expect($price->cents())->toBe(0);
    expect($price->isFree())->toBeTrue();
});

test('price converts euros correctly', function () {
    $price = Price::fromCents(3450);
    expect($price->euros())->toBe(34.50);
});

test('price rejects negative amounts', function () {
    expect(fn () => Price::fromCents(-100))
        ->toThrow(InvalidArgumentException::class);
});

test('price rejects negative euros', function () {
    expect(fn () => Price::fromEuros(-10.00))
        ->toThrow(InvalidArgumentException::class);
});

test('price can add amounts', function () {
    $price1 = Price::fromEuros(15.00);
    $price2 = Price::fromEuros(10.00);
    $result = $price1->add($price2);

    expect($result->euros())->toBe(25.00);
});

test('price can subtract amounts', function () {
    $price1 = Price::fromEuros(20.00);
    $price2 = Price::fromEuros(5.00);
    $result = $price1->subtract($price2);

    expect($result->euros())->toBe(15.00);
});

test('price can multiply by quantity', function () {
    $price = Price::fromEuros(10.00);
    $result = $price->multiply(3);

    expect($result->euros())->toBe(30.00);
});

test('price recognizes free pricing', function () {
    $free = Price::free();
    $paid = Price::fromEuros(5.00);

    expect($free->isFree())->toBeTrue();
    expect($paid->isFree())->toBeFalse();
});

test('price can be compared for equality', function () {
    $price1 = Price::fromEuros(25.00);
    $price2 = Price::fromEuros(25.00);
    $price3 = Price::fromEuros(15.00);

    expect($price1->equals($price2))->toBeTrue();
    expect($price1->equals($price3))->toBeFalse();
});

test('price can be compared for greater than', function () {
    $price1 = Price::fromEuros(20.00);
    $price2 = Price::fromEuros(10.00);

    expect($price1->isGreaterThan($price2))->toBeTrue();
    expect($price2->isGreaterThan($price1))->toBeFalse();
});

test('price can be compared for less than', function () {
    $price1 = Price::fromEuros(10.00);
    $price2 = Price::fromEuros(20.00);

    expect($price1->isLessThan($price2))->toBeTrue();
    expect($price2->isLessThan($price1))->toBeFalse();
});

test('price converts to string', function () {
    $price = Price::fromEuros(49.99);
    expect((string) $price)->toContain('49,99');
});

test('price subtraction handles zero result', function () {
    $price1 = Price::fromEuros(10.00);
    $price2 = Price::fromEuros(10.00);
    $result = $price1->subtract($price2);

    expect($result->euros())->toBe(0.00);
    expect($result->isFree())->toBeTrue();
});
