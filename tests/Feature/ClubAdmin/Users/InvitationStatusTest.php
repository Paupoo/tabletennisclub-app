<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

test('user with email verified is active', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'last_invited_at' => null,
    ]);

    expect($user->invitationStatus())->toBe('active');
});

test('user invited within 48h without verification is pending', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'last_invited_at' => now()->subHours(12),
    ]);

    expect($user->invitationStatus())->toBe('pending');
});

test('user invited more than 48h ago without verification is expired', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'last_invited_at' => now()->subHours(72),
    ]);

    expect($user->invitationStatus())->toBe('expired');
});

test('user never invited and not verified has no invitation status', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'last_invited_at' => null,
    ]);

    expect($user->invitationStatus())->toBe('not_invited');
});
