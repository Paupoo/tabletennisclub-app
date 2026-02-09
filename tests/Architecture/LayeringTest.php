<?php

declare(strict_types=1);

test('controllers ne dépendent pas des repositories directement')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Repositories');

test('models ne dépendent que de Eloquent')
    ->expect('App\Models')
    ->toOnlyUse([
        'Illuminate\Database\Eloquent',
        'Illuminate\Support',
        'Carbon\Carbon',
    ]);

test('services peuvent utiliser repositories')
    ->expect('App\Services')
    ->toUse('App\Repositories');


