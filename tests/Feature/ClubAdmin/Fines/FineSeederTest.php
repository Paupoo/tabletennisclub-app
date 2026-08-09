<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Database\Seeders\FineSeeder;

it('seeds a demo fine with its pending payment for user #1', function (): void {
    $member = User::factory()->create();
    expect($member->id)->toBe(1);

    $this->seed(FineSeeder::class);

    $fine = Fine::first();

    expect($fine)->not->toBeNull()
        ->and($fine->user_id)->toBe(1)
        ->and($fine->amount)->toBe(15.0)
        ->and($fine->reason)->toBe(FineReason::UNJUSTIFIED_ABSENCE)
        ->and($fine->payment)->not->toBeNull()
        ->and($fine->payment->status)->toBe('pending')
        ->and($fine->payment->amount_due)->toBe(15.0);
});

it('skips seeding when there is no user #1', function (): void {
    $this->seed(FineSeeder::class);

    expect(Fine::count())->toBe(0);
});
