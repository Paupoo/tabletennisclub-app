<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;

test('normalizes bank_account on assignment, stripping spaces and uppercasing', function (): void {
    $club = new Club;
    $club->bank_account = 'be68 5390 0754 7034';

    expect($club->bank_account)->toBe('BE68539007547034');
});

test('exposes bank_account_formatted grouped by 4 for display', function (): void {
    $club = new Club;
    $club->bank_account = 'BE68539007547034';

    expect($club->bank_account_formatted)->toBe('BE68 5390 0754 7034');
});
