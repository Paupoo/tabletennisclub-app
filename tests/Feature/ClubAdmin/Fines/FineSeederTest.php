<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Services\FineCreditor;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use Database\Seeders\FineSeeder;

it('seeds the committee account and one fine per case the screens tell apart', function (): void {
    $member = User::factory()->create();
    expect($member->id)->toBe(1);
    $minor = User::factory()->create(['birthdate' => now()->subYears(12)]);
    $minor->guardians()->attach(Guardian::factory()->create()->id);

    $this->seed(FineSeeder::class);

    expect(app(FineCreditor::class)->isConfigured())->toBeTrue()
        ->and(Fine::where('user_id', 1)->get()->filter->isPayable())->toHaveCount(1)
        ->and(Fine::where('user_id', 1)->get()->reject->isPayable())->toHaveCount(1)
        ->and(Fine::where('user_id', $minor->id)->count())->toBe(1)
        ->and(Fine::onlyTrashed()->count())->toBe(1)
        ->and(Payment::count())->toBe(0);
});

it('skips the fines when there is no user #1', function (): void {
    $this->seed(FineSeeder::class);

    expect(Fine::count())->toBe(0);
});
