<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;

test('setting a new own club demotes the previous one', function (): void {
    $first = Club::factory()->ownClub()->create();
    $second = Club::factory()->create();

    $second->update(['is_own_club' => true]);

    expect($first->fresh()->is_own_club)->toBeFalse();
    expect($second->fresh()->is_own_club)->toBeTrue();
    expect(Club::where('is_own_club', true)->count())->toBe(1);
});

test('creating a club as own club demotes an existing own club', function (): void {
    $first = Club::factory()->ownClub()->create();
    $second = Club::factory()->ownClub()->create();

    expect($first->fresh()->is_own_club)->toBeFalse();
    expect(Club::own()->is($second))->toBeTrue();
});

test('saving an own club without touching the flag leaves others untouched', function (): void {
    $own = Club::factory()->ownClub()->create();
    $other = Club::factory()->create();

    $own->update(['name' => 'Renamed Club']);

    expect($own->fresh()->is_own_club)->toBeTrue();
    expect($other->fresh()->is_own_club)->toBeFalse();
});
