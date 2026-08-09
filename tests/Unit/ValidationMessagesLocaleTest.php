<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;

it('renders validation messages in French for the fr_BE locale', function (): void {
    app()->setLocale('fr_BE');

    $validator = Validator::make(
        ['email' => ''],
        ['email' => 'required|email'],
    );

    expect($validator->errors()->first('email'))
        ->toBe('Le champ adresse e-mail est obligatoire.');
});

it('translates the technical field path into a readable label', function (): void {
    app()->setLocale('fr_BE');

    $validator = Validator::make(
        ['start_date' => 'not-a-date'],
        ['start_date' => 'date'],
    );

    expect($validator->errors()->first('start_date'))
        ->toBe('Le champ date de début doit être une date valide.');
});
