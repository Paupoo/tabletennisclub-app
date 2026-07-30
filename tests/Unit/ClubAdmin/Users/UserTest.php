<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Carbon\Carbon;

test('method set age', function (): void {
    // Start
    $user = new User;
    $user->birthdate = '1988-08-17';
    $age = Carbon::parse($user->birthdate)->age;

    // Change
    $user->setAge();

    // Assert
    expect($user->age)->toEqual($age);
});
test('method set age without birthdate', function (): void {
    // Start
    $user = new User;

    // Change
    $user->setAge();

    // Assert
    expect($user->age)->toEqual('Unknown');
});
test('method set first name attribute', function (): void {
    $user = new User;
    $user->first_name = 'aURÉliEN';

    expect($user->first_name)->toEqual('Aurélien');
});
test('normalizes iban on assignment, stripping spaces and uppercasing', function (): void {
    $user = new User;
    $user->iban = 'be68 5390 0754 7034';

    expect($user->iban)->toBe('BE68539007547034');
});

test('exposes iban_formatted grouped by 4 for display', function (): void {
    $user = new User;
    $user->iban = 'BE68539007547034';

    expect($user->iban_formatted)->toBe('BE68 5390 0754 7034');
});

test('iban_formatted is null when iban is not set', function (): void {
    $user = new User;

    expect($user->iban_formatted)->toBeNull();
});

test('method set last name attribute', function (): void {
    $user = new User;
    $user->first_name = 'pAULUS';

    expect($user->first_name)->toEqual('Paulus');
});

test('normalizes the street on assignment, whatever wrote it', function (): void {
    $user = new User;
    $user->street = 'AVENUE DE L\'EXEMPLE 12A';

    expect($user->street)->toBe('Avenue de l\'Exemple 12A');
});

test('normalizes the city name on assignment', function (): void {
    $user = new User;
    $user->city_name = 'LOUVAIN-LA-NEUVE';

    expect($user->city_name)->toBe('Louvain-la-Neuve');
});

/*
 * Both columns are nullable, unlike the names: a member the club reaches through
 * a guardian has no address of their own.
 */
test('accepts a null address without normalizing anything', function (): void {
    $user = new User;
    $user->street = null;
    $user->city_name = null;

    expect($user->street)->toBeNull()
        ->and($user->city_name)->toBeNull();
});
