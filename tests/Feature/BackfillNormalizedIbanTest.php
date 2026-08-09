<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use Illuminate\Support\Facades\DB;

function runBackfillNormalizedIbanMigration(): void
{
    $migration = require base_path('database/migrations/2026_07_05_225343_backfill_normalized_iban.php');
    $migration->up();
}

it('backfills users, guardians, and clubs with a spaced iban already stored', function (): void {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['iban' => 'BE68 5390 0754 7034']);

    $guardian = Guardian::factory()->create();
    DB::table('guardians')->where('id', $guardian->id)->update(['iban' => 'be68 5390 0754 7034']);

    $club = Club::factory()->ownClub()->create();
    DB::table('clubs')->where('id', $club->id)->update(['bank_account' => 'BE68 5390 0754 7034']);

    runBackfillNormalizedIbanMigration();

    expect(DB::table('users')->find($user->id)->iban)->toBe('BE68539007547034')
        ->and(DB::table('guardians')->find($guardian->id)->iban)->toBe('BE68539007547034')
        ->and(DB::table('clubs')->find($club->id)->bank_account)->toBe('BE68539007547034');
});
