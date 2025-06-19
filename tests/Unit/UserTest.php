<?php

declare(strict_types=1);
use App\Models\User;
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
test('method set last name attribute', function (): void {
    $user = new User;
    $user->first_name = 'pAULUS';

    expect($user->first_name)->toEqual('Paulus');
});
