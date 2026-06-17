<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Spam;
use Database\Seeders\SpamSeeder;

it('seeds 10 blocked spams', function (): void {
    $this->seed(SpamSeeder::class);

    expect(Spam::count())->toBe(10)
        ->and(Spam::where('is_blocked', true)->count())->toBe(10);
});
