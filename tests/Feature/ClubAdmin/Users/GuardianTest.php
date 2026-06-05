<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;

test('guardian can be created with required fields', function (): void {
    $guardian = Guardian::factory()->create([
        'first_name' => 'Marie',
        'last_name' => 'Dupont',
        'phone' => '0479123456',
        'email' => 'marie.dupont@example.com',
    ]);

    expect($guardian->first_name)->toBe('Marie')
        ->and($guardian->last_name)->toBe('Dupont')
        ->and($guardian->phone)->toBe('0479123456')
        ->and($guardian->email)->toBe('marie.dupont@example.com')
        ->and($guardian->iban)->toBeNull();
});

test('guardian can store optional iban', function (): void {
    $guardian = Guardian::factory()->create(['iban' => 'BE68539007547034']);

    expect($guardian->iban)->toBe('BE68539007547034');
});

test('user has guardians relationship pointing to Guardian model', function (): void {
    $user = User::factory()->create();
    $guardian = Guardian::factory()->create();

    $user->guardians()->attach($guardian);

    expect($user->guardians)->toHaveCount(1)
        ->and($user->guardians->first())->toBeInstanceOf(Guardian::class)
        ->and($user->guardians->first()->id)->toBe($guardian->id);
});

test('guardian has users relationship', function (): void {
    $guardian = Guardian::factory()->create();
    $sibling1 = User::factory()->create();
    $sibling2 = User::factory()->create();

    $guardian->users()->attach([$sibling1->id, $sibling2->id]);

    expect($guardian->users)->toHaveCount(2);
});

test('one guardian can cover multiple siblings', function (): void {
    $guardian = Guardian::factory()->create();
    $children = User::factory()->count(3)->create();

    $guardian->users()->attach($children->pluck('id'));

    expect($guardian->users()->count())->toBe(3);
});

test('user is considered minor when under 18', function (): void {
    $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);
    $adult = User::factory()->create(['birthdate' => now()->subYears(20)]);

    expect($minor->isMinor())->toBeTrue()
        ->and($adult->isMinor())->toBeFalse();
});

test('minor without guardian is flagged as requiring one', function (): void {
    $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);

    expect($minor->requiresGuardian())->toBeTrue()
        ->and($minor->hasGuardian())->toBeFalse();
});

test('minor with guardian attached is not flagged', function (): void {
    $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);
    $guardian = Guardian::factory()->create();
    $minor->guardians()->attach($guardian);

    expect($minor->hasGuardian())->toBeTrue();
});
