<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Database\Seeders\DoublesTournamentRehearsalSeeder;

/*
 * The rehearsal is only worth it on a field shaped like the club's: pairs
 * enough to fill four pools, children at the reduced price, and accounts a
 * parent holds.
 */

it('seeds an open doubles tournament of sixteen pairs, shaped like the club', function (): void {
    makeActiveSeason();

    $this->seed(DoublesTournamentRehearsalSeeder::class);

    $tournament = Tournament::where('name', DoublesTournamentRehearsalSeeder::NAME)->sole();
    $registrations = TournamentRegistration::where('tournament_id', $tournament->id)->with(['user.guardians', 'payment'])->get();

    expect($tournament->status)->toBe(TournamentStatusEnum::PUBLISHED)
        ->and($tournament->match_type)->toBe('double')
        ->and($tournament->bracket_size)->toBe(8)
        ->and($registrations)->toHaveCount(32)
        ->and($registrations->filter(fn (TournamentRegistration $r): bool => $r->payment?->status === 'paid'))->toHaveCount(22)
        ->and($registrations->filter(fn (TournamentRegistration $r): bool => (float) $r->payment?->amount_due === 10.0)->count())->toBeGreaterThan(5)
        ->and($registrations->filter(fn (TournamentRegistration $r): bool => $r->user->email === null && $r->user->guardians->isNotEmpty())->count())->toBeGreaterThan(2)
        ->and(User::active()->whereNotIn('id', $registrations->pluck('user_id'))->whereIn('last_name', ['Pauly', 'Ronveaux'])->count())->toBe(2);
});

it('seeds it once', function (): void {
    makeActiveSeason();

    $this->seed(DoublesTournamentRehearsalSeeder::class);
    $this->seed(DoublesTournamentRehearsalSeeder::class);

    expect(Tournament::where('name', DoublesTournamentRehearsalSeeder::NAME)->count())->toBe(1);
});
