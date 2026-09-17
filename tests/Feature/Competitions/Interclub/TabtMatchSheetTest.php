<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Services\TabtClient;
use Illuminate\Support\Facades\Http;

function sheetFixture(): string
{
    return file_get_contents(base_path('tests/Fixtures/Aftt/get-matches-with-details.xml'));
}

function sheets(): array
{
    Http::fake(['api.aftt.be/*' => Http::response(sheetFixture())]);

    return app(TabtClient::class)->divisionMatchSheets(8860, 26);
}

it('reads every fixture of the division, encoded or not', function (): void {
    $all = sheets();

    expect($all)->toHaveCount(4)
        ->and(collect($all)->filter(fn ($s): bool => $s->detailsCreated))->toHaveCount(3);
});

it('leaves an unplayed fixture empty rather than inventing a sheet', function (): void {
    $pending = collect(sheets())->firstWhere('matchId', 'PBBWH01/001');

    expect($pending->detailsCreated)->toBeFalse()
        ->and($pending->score)->toBeNull()
        ->and($pending->results)->toBe([])
        ->and($pending->homePlayers)->toBe([]);
});

it('reads a system 2 sheet: four a side, sixteen singles, no double', function (): void {
    $sheet = collect(sheets())->firstWhere('matchId', 'PBBWH01/021');

    expect($sheet->score)->toBe('9-7')
        ->and($sheet->matchSystem)->toBe(2)
        ->and($sheet->homePlayers)->toHaveCount(4)
        ->and($sheet->awayPlayers)->toHaveCount(4)
        ->and($sheet->results)->toHaveCount(16)
        ->and(collect($sheet->results)->filter(fn ($r): bool => $r->isDouble()))->toBeEmpty();

    $first = $sheet->homePlayers[0];
    expect($first->uniqueIndex)->toBe('166488')
        ->and($first->fullName())->toBe('AARON DAVRIL')
        ->and($first->ranking)->toBe('C2');
});

it('names nobody on the double, because the federation names nobody', function (): void {
    $sheet = collect(sheets())->firstWhere('matchId', 'PBBWV01/305');

    $doubles = collect($sheet->results)->filter(fn ($r): bool => $r->isDouble());

    expect($sheet->matchSystem)->toBe(4)
        ->and($sheet->homePlayers)->toHaveCount(3)
        ->and($sheet->results)->toHaveCount(10)
        ->and($doubles)->toHaveCount(1);

    $double = $doubles->first();
    expect($double->position)->toBe(7)
        ->and($double->homeUniqueIndex)->toBeNull()
        ->and($double->awayUniqueIndex)->toBeNull()
        ->and($double->homeWon())->toBeTrue();
});

it('keeps both players on a forfeited line, with no sets', function (): void {
    $sheet = collect(sheets())->firstWhere('matchId', 'PBBWH01/022');

    $forfeit = collect($sheet->results)->first(fn ($r): bool => $r->isAwayForfeited);

    expect($forfeit)->not->toBeNull()
        ->and($forfeit->homeUniqueIndex)->not->toBeNull()
        ->and($forfeit->awayUniqueIndex)->not->toBeNull()
        ->and($forfeit->homeSetCount)->toBeNull()
        ->and($forfeit->homeWon())->toBeTrue();
});

it('never lets victory counts stand in for the team score', function (): void {
    // The point of deriving wins from the lines: in system 4 the counts always
    // fall short by the double, and a forfeited player has no count at all.
    $veterans = collect(sheets())->firstWhere('matchId', 'PBBWV01/305');
    $counted = collect($veterans->homePlayers)->sum(fn ($p): int => $p->victoryCount ?? 0)
        + collect($veterans->awayPlayers)->sum(fn ($p): int => $p->victoryCount ?? 0);

    expect($counted)->toBe(9)
        ->and($veterans->score)->toBe('9-1');

    $derived = collect($veterans->results)->filter(fn ($r): bool => $r->homeWon() !== null);
    expect($derived)->toHaveCount(10);
});
