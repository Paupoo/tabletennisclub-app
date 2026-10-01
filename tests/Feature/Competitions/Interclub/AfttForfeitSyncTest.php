<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Services\AfttCalendarImporter;
use App\Domains\Shared\Enums\InterclubForfeit;
use App\Domains\Shared\Enums\InterclubResultEnum;

/*
 * The federation publishes a forfeit before the evening it cancels: on
 * 2026-10-01, PBBWH03/076 (our F against La Hulpe-Rixensart I, due the next
 * day) already read "16-0 ff", IsAwayForfeited, validated and locked, with no
 * sheet encoded. The patches below reproduce those fields on the committed
 * fixture PBBWH01/113 — our E at home against Hamme Mille D.
 */

beforeEach(function (): void {
    afttClubTeams('get-club-teams-bbw214-two-divisions.xml');
    afttFaultOn(reset: true);
    fakeTabt();
    knownOpponents();

    $this->season = Season::factory()->create(['name' => '2026-2027']);
    Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);
});

function syncCalendar(): void
{
    app(AfttCalendarImporter::class)->import(test()->season, 27, 'BBW214');
}

/** As PBBWH03/076 read on 2026-10-01. */
function opponentForfeits(string $matchId = 'PBBWH01/113'): void
{
    afttPatchMatch($matchId, [
        'Score' => '16-0 ff',
        'IsAwayForfeited' => 'true',
        'IsValidated' => 'true',
        'IsLocked' => 'true',
    ]);
}

it('records an opponent forfeit published before the evening, and the win it gives us', function (): void {
    opponentForfeits();

    syncCalendar();

    $fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();

    expect($fixture->forfeit)->toBe(InterclubForfeit::OPPONENT_FORFEIT)
        ->and($fixture->interclubResult->result)->toBe(InterclubResultEnum::FORFEIT_WIN)
        ->and($fixture->interclubResult->score)->toBe('16-0');
});

it('tells a withdrawal from a forfeit by the withdrawal flag, since both sides read forfeited', function (): void {
    // As Arc En Ciel F read in 2025-2026 once it had left the division.
    afttPatchMatch('PBBWH01/113', [
        'Score' => '0-0 fg',
        'IsHomeForfeited' => 'true',
        'IsAwayForfeited' => 'true',
        'IsAwayWithdrawn' => '1',
        'IsLocked' => 'true',
    ]);

    syncCalendar();

    $fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();

    expect($fixture->forfeit)->toBe(InterclubForfeit::OPPONENT_WITHDRAWAL)
        ->and($fixture->interclubResult->result)->toBe(InterclubResultEnum::WITHDRAWAL_OPPONENT)
        ->and($fixture->interclubResult->score)->toBe('0-0');
});

it('records our own forfeit as a loss', function (): void {
    afttPatchMatch('PBBWH01/113', [
        'Score' => '0-16 ff',
        'IsHomeForfeited' => 'true',
        'IsLocked' => 'true',
    ]);

    syncCalendar();

    $fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();

    expect($fixture->forfeit)->toBe(InterclubForfeit::OUR_FORFEIT)
        ->and($fixture->interclubResult->result)->toBe(InterclubResultEnum::FORFEIT_LOSS);
});

it('marks a forfeit the federation has not locked yet, without deciding the result', function (): void {
    afttPatchMatch('PBBWH01/113', ['Score' => '16-0 ff', 'IsAwayForfeited' => 'true']);

    syncCalendar();

    $fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();

    expect($fixture->forfeit)->toBe(InterclubForfeit::OPPONENT_FORFEIT)
        ->and($fixture->interclubResult->result)->toBeNull();
});

it('gives the fixture back when the federation retracts the forfeit', function (): void {
    opponentForfeits();
    syncCalendar();

    afttPatchMatch(reset: true);
    syncCalendar();

    $fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();

    expect($fixture->forfeit)->toBeNull()
        ->and($fixture->interclubResult->result)->toBeNull()
        ->and($fixture->interclubResult->score)->toBeNull();
});

it('keeps a score somebody entered after the forfeit, when the federation retracts it', function (): void {
    opponentForfeits();
    syncCalendar();

    $fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();
    $fixture->interclubResult->update(['score' => '9-7', 'result' => InterclubResultEnum::WIN->value]);

    afttPatchMatch(reset: true);
    syncCalendar();

    expect($fixture->fresh()->forfeit)->toBeNull()
        ->and($fixture->interclubResult->fresh()->result)->toBe(InterclubResultEnum::WIN)
        ->and($fixture->interclubResult->fresh()->score)->toBe('9-7');
});

it('lets a locked forfeit overrule a score a captain typed', function (): void {
    syncCalendar();
    $fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();
    $fixture->interclubResult->update(['score' => '9-7', 'result' => InterclubResultEnum::WIN->value]);

    opponentForfeits();
    syncCalendar();

    expect($fixture->interclubResult->fresh()->result)->toBe(InterclubResultEnum::FORFEIT_WIN)
        ->and($fixture->interclubResult->fresh()->score)->toBe('16-0');
});
