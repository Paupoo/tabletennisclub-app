<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Services\TabtClient;

/**
 * Calls the federation for real, and checks that what the importer reads is
 * still there.
 *
 * Deliberately outside the suites `composer test` runs. The rest of the tests
 * are driven by committed fixtures, which is what keeps them fast and offline —
 * and also what makes them blind: they will keep passing long after the
 * federation changes its API. This is the canary for that, and it is run on
 * purpose, by a person or a schedule, never as part of the default suite.
 *
 * Run it with: php artisan test --testsuite=Contract
 *
 * It asserts shapes and never counts. Fixtures move, teams withdraw, and a test
 * that insisted on 129 matches would fail every week for no reason.
 */
beforeEach(function (): void {
    $this->client = app(TabtClient::class);
    $this->club = 'BBW214';
});

it('still publishes a current season with a readable name', function (): void {
    $seasons = $this->client->seasons();

    expect($seasons->currentSeason)->toBeGreaterThan(0)
        ->and($seasons->currentSeasonName)->toMatch('/^\d{4}-\d{4}$/')
        ->and($seasons->all)->not->toBeEmpty()
        ->and($seasons->all[$seasons->currentSeason])->toBe($seasons->currentSeasonName);
});

it('still lists our club’s teams with the division each plays in', function (): void {
    $season = $this->client->seasons()->currentSeason;
    $teams = $this->client->clubTeams($this->club, $season);

    expect($teams)->not->toBeEmpty();

    foreach ($teams as $team) {
        expect($team->letter)->toMatch('/^[A-Z]$/')
            ->and($team->divisionId)->toBeGreaterThan(0)
            ->and($team->divisionCategory)->toBeGreaterThan(0);
    }
});

it('still carries the division level, which lives nowhere else', function (): void {
    $season = $this->client->seasons()->currentSeason;
    $ourDivisions = collect($this->client->clubTeams($this->club, $season))->pluck('divisionId');
    $divisions = $this->client->divisions($season);

    foreach ($ourDivisions as $id) {
        expect($divisions)->toHaveKey($id)
            ->and($divisions[$id]->level)->toBeGreaterThan(0)
            ->and($divisions[$id]->category)->toBeGreaterThan(0);
    }
});

it('still gives a fixture an id, a moment and a venue', function (): void {
    $season = $this->client->seasons()->currentSeason;
    $divisionId = collect($this->client->clubTeams($this->club, $season))->first()->divisionId;

    $matches = collect($this->client->divisionMatches($divisionId, $season));

    expect($matches)->not->toBeEmpty();

    $played = $matches->reject(fn ($match): bool => $match->isBye);

    expect($played)->not->toBeEmpty();

    foreach ($played as $match) {
        expect($match->matchId)->not->toBe('')
            ->and($match->weekName)->not->toBe('')
            ->and($match->homeClub)->not->toBe('')
            ->and($match->awayClub)->not->toBe('');
    }

    // At least one fixture states where it is played. If this ever fails, every
    // imported address silently becomes the empty string.
    expect($played->filter(fn ($match): bool => $match->venue !== null))->not->toBeEmpty();
});

it('still writes a bye as a fixture with no opponent and no date', function (): void {
    $season = $this->client->seasons()->currentSeason;

    $byes = collect($this->client->clubTeams($this->club, $season))
        ->flatMap(fn ($team): array => $this->client->divisionMatches($team->divisionId, $season))
        ->filter(fn ($match): bool => $match->isBye);

    // Byes are not guaranteed to exist in a given season — an even division has
    // none — so this asserts their shape only when the federation publishes any.
    foreach ($byes as $bye) {
        expect($bye->date)->toBeNull()
            ->and($bye->venue)->toBeNull()
            ->and($bye->matchId)->not->toBe('');
    }
});

it('still answers to the request element names the client sends', function (): void {
    $season = $this->client->seasons()->currentSeason;

    // GetMatches wants "GetMatchesRequest" while GetDivisions and GetClubs want
    // their bare names. Getting this wrong returns a SOAP fault, not an error
    // anybody could act on, so it is worth an explicit check.
    expect(fn () => $this->client->divisions($season))->not->toThrow(RuntimeException::class)
        ->and(fn () => $this->client->clubTeams($this->club, $season))->not->toThrow(RuntimeException::class)
        ->and(fn () => $this->client->club($this->club, $season))->not->toThrow(RuntimeException::class);
});
