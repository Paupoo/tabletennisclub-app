<?php

declare(strict_types=1);
use App\Support\Captcha;

describe('Captcha', function (): void {
    it('returns an array with a, b and operation', function (): void {
        $captcha = Captcha::generate();

        expect($captcha)->toHaveKeys(['a', 'b', 'operation']);
    });

    test('a and b are integers between 0 and 10', function (): void {
        $captcha = Captcha::generate();

        expect($captcha['a'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
        expect($captcha['b'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
    });

    test('operation is either + or *', function (): void {
        $captcha = Captcha::generate();

        expect($captcha['operation'])->toBeIn(['+', '*']);
    });

    it('validates the captcha correctly', function (): void {
        $captcha = new Captcha;

        // Test addition
        $captchaData = ['a' => 2, 'b' => 3, 'operation' => '+'];
        expect($captcha->validate($captchaData, 5))->toBeTrue();
        expect($captcha->validate($captchaData, 4))->toBeFalse();

        // Test multiplication
        $captchaData = ['a' => 4, 'b' => 5, 'operation' => '*'];
        expect($captcha->validate($captchaData, 20))->toBeTrue();
        expect($captcha->validate($captchaData, 19))->toBeFalse();
    });

    it('returns false for invalid operations', function (): void {
        $captcha = new Captcha;

        $captchaData = ['a' => 2, 'b' => 3, 'operation' => '-'];
        expect(fn (): bool => $captcha->validate($captchaData, 5))->toThrow(InvalidArgumentException::class);
    });

    it('throws an error for non-integer user results', function (): void {
        $captcha = new Captcha;

        $captchaData = ['a' => 2, 'b' => 3, 'operation' => '+'];
        expect(fn (): bool => $captcha->validate($captchaData, '5'))->toThrow(TypeError::class);
        expect(fn (): bool => $captcha->validate($captchaData, 5.0))->toThrow(TypeError::class);
    });

    it('returns false for missing keys in captcha data', function (): void {
        $captcha = new Captcha;

        $captchaData = ['a' => 2, 'operation' => '+'];
        expect($captcha->validate($captchaData, 5))->toBeFalse();

        $captchaData = ['b' => 3, 'operation' => '+'];
        expect($captcha->validate($captchaData, 5))->toBeFalse();

        $captchaData = ['a' => 2, 'b' => 3];
        expect($captcha->validate($captchaData, 5))->toBeFalse();
    });

    it('throws an error for non-array captcha data', function (): void {
        $captcha = new Captcha;

        expect(fn (): bool => $captcha->validate('not an array', 5))->toThrow(TypeError::class);
    });

    it('throws an error for null captcha data', function (): void {
        $captcha = new Captcha;

        expect(fn (): bool => $captcha->validate(null, 5))->toThrow(TypeError::class);
    });

    it('returns false for empty captcha data', function (): void {
        $captcha = new Captcha;

        expect($captcha->validate([], 5))->toBeFalse();
    });

    it('throw an error for non-integer a or b values', function (): void {
        $captcha = new Captcha;

        $captchaData = ['a' => 'not an integer', 'b' => 3, 'operation' => '+'];
        expect(fn (): bool => $captcha->validate($captchaData, 5))->toThrow(TypeError::class);

        $captchaData = ['a' => 2, 'b' => 'not an integer', 'operation' => '+'];
        expect(fn (): bool => $captcha->validate($captchaData, 5))->toThrow(TypeError::class);
    });

    it('returns false for a or b values out of range', function (): void {
        $captcha = new Captcha;

        $captchaData = ['a' => -1, 'b' => 3, 'operation' => '+'];
        expect(fn (): bool => $captcha->validate($captchaData, 5))->toThrow(InvalidArgumentException::class);

        $captchaData = ['a' => 2, 'b' => 11, 'operation' => '+'];
        expect(fn (): bool => $captcha->validate($captchaData, 5))->toThrow(InvalidArgumentException::class);
    });

})->group('unit', 'support', 'captcha');
