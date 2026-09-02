<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Services\TabtClient;
use Illuminate\Support\Facades\Http;

function afttFixture(string $name): string
{
    return file_get_contents(base_path('tests/Fixtures/Aftt/' . $name));
}

it('reads the season the federation considers current', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::response(afttFixture('get-seasons.xml')),
    ]);

    $seasons = app(TabtClient::class)->seasons();

    expect($seasons->currentSeason)->toBe(27)
        ->and($seasons->currentSeasonName)->toBe('2026-2027');

    // The whole list too, so a season can be loaded before it becomes current.
    expect($seasons->all[26])->toBe('2025-2026')
        ->and($seasons->all[27])->toBe('2026-2027')
        ->and($seasons->all[1])->toBe('2000-2001');
});

it('reads the divisions our own teams play in', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::response(afttFixture('get-club-teams-bbw214.xml')),
    ]);

    $teams = app(TabtClient::class)->clubTeams('BBW214', 27);

    expect($teams)->toHaveCount(9);

    $veteransA = collect($teams)->firstWhere('divisionId', 9756);

    expect($veteransA->letter)->toBe('A')
        ->and($veteransA->divisionName)->toBe('Division 3D - Prov. B.B.W. - Vétérans')
        ->and($veteransA->divisionCategory)->toBe(3);

    expect(collect($teams)->pluck('divisionId')->all())
        ->toEqualCanonicalizing([9756, 9755, 9761, 9589, 9594, 9600, 9599, 9611, 9604]);
});

it('reads a fixture with its venue', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::response(afttFixture('get-matches-division-9611.xml')),
    ]);

    $matches = app(TabtClient::class)->divisionMatches(9611, 27);

    expect($matches)->toHaveCount(90);

    $match = collect($matches)->firstWhere('matchId', 'PBBWH01/113');

    expect($match->weekName)->toBe('01')
        ->and($match->date->toDateString())->toBe('2026-09-18')
        ->and($match->time)->toBe('19:45:00')
        ->and($match->homeClub)->toBe('BBW214')
        ->and($match->homeTeam)->toBe('CTT Ottignies - Blocry E')
        ->and($match->awayClub)->toBe('BBW299')
        ->and($match->awayTeam)->toBe('Hamme Mille D')
        ->and($match->divisionId)->toBe(9611)
        ->and($match->divisionCategory)->toBe(37)
        ->and($match->isBye)->toBeFalse();

    expect($match->venue->name)->toBe('COMPLEXE SPORTIF JEAN DEMEESTER')
        ->and($match->venue->street)->toBe("RUE DE L'INVASION, 80")
        ->and($match->venue->town)->toBe('1340 OTTIGNIES LLN');
});

it('recognises a bye, which has no opponent and no date', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::response(afttFixture('get-matches-division-9611.xml')),
    ]);

    $matches = app(TabtClient::class)->divisionMatches(9611, 27);

    $bye = collect($matches)->firstWhere('matchId', 'PBBWH05/114');

    expect($bye->isBye)->toBeTrue()
        ->and($bye->weekName)->toBe('05')
        ->and($bye->date)->toBeNull()
        ->and($bye->time)->toBeNull()
        ->and($bye->venue)->toBeNull()
        ->and($bye->homeClub)->toBe('BBW214');
});

it('reads a club and the hall it plays in', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::response(afttFixture('get-clubs-bbw145.xml')),
    ]);

    $club = app(TabtClient::class)->club('BBW145', 27);

    expect($club->licence)->toBe('BBW145')
        ->and($club->name)->toBe('Muppets')
        ->and($club->longName)->toBe("Muppet's TT Auderghem")
        ->and($club->venue->street)->toBe('CHAUSSEE DE WAVRE 1690')
        ->and($club->venue->town)->toBe('1160 BRUXELLES');
});

it('returns nothing for a club the federation does not know', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::response(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" '
            . 'xmlns:ns1="http://api.frenoy.net/TabTAPI"><SOAP-ENV:Body><ns1:GetClubsResponse>'
            . '<ns1:ClubCount>0</ns1:ClubCount></ns1:GetClubsResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>'
        ),
    ]);

    expect(app(TabtClient::class)->club('XXX999', 27))->toBeNull();
});

it('reads the level of a division, which only the division list carries', function (): void {
    Http::fake([
        'api.aftt.be/*' => Http::response(afttFixture('get-divisions.xml')),
    ]);

    $divisions = app(TabtClient::class)->divisions(27);

    expect($divisions[9756]->level)->toBe(11)
        ->and($divisions[9756]->category)->toBe(3)
        ->and($divisions[9756]->name)->toBe('Division 3D - Prov. B.B.W. - Vétérans')
        ->and($divisions[9496]->level)->toBe(1)
        ->and($divisions[9565]->level)->toBe(16)
        ->and($divisions[9824]->category)->toBe(41);
});
