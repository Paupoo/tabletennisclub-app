<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
|--------------------------------------------------------------------------
| Global password policy (AppServiceProvider)
|--------------------------------------------------------------------------
|
| Password::defaults() is hardened app-wide: min 8 chars, letters and
| numbers. The haveibeenpwned check only runs in production so tests
| never depend on the network.
|
*/

function passwordPasses(string $password): bool
{
    return Validator::make(
        ['password' => $password],
        ['password' => Password::defaults()]
    )->passes();
}

test('the default policy accepts 8+ chars mixing letters and numbers', function (): void {
    expect(passwordPasses('longer12'))->toBeTrue()
        ->and(passwordPasses('new-password-123'))->toBeTrue();
});

test('the default policy rejects weak passwords', function (string $password): void {
    expect(passwordPasses($password))->toBeFalse();
})->with([
    'too short' => 'short12',
    'letters only' => 'lettersonly',
    'numbers only' => '12345678',
]);

test('the compromised check is disabled outside production (no network in tests)', function (): void {
    // "password123" tops the haveibeenpwned lists; it must pass here because
    // uncompromised() is production-only.
    expect(passwordPasses('password123'))->toBeTrue();
});
