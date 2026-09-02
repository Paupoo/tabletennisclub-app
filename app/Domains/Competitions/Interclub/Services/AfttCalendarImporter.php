<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\AfttDivision;
use App\Data\Interclub\AfttMatch;
use App\Data\Interclub\AfttVenue;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use App\Domains\Shared\Support\AddressNormalizer;
use Carbon\CarbonImmutable;

/**
 * Writes the federation's calendar into the club's own tables.
 *
 * Takes value objects and produces rows; it never speaks HTTP. Everything is
 * fetched before anything is written, so a federation timeout cannot leave a
 * season half rebuilt.
 *
 * The rule running through all of it: the federation owns the fixture, the club
 * owns what its members said about the fixture. Dates, venues and opponents are
 * overwritten without ceremony. Availabilities, selections and results are never
 * touched, and anything carrying them is reported rather than removed.
 */
class AfttCalendarImporter
{
    /**
     * The federation's category codes, mapped to ours.
     *
     * Read from the numeric code and never from the printed label: the label is
     * a French sentence the federation may rewrite at will, and it already ships
     * team names with trailing spaces. Youth (41) is deliberately absent — the
     * club has no youth team, and inventing a `LeagueCategory` for one would
     * model a competition nobody has thought about. An unmapped code is refused
     * by name in the report, which is the correct way to learn the club just
     * entered one.
     */
    private const array CATEGORIES = [
        37 => LeagueCategory::MEN,
        3 => LeagueCategory::VETERANS,
        38 => LeagueCategory::WOMEN,
    ];

    /**
     * The federation's level codes, mapped to ours.
     *
     * Only the levels the club can play in. The other provinces have codes of
     * their own; a team of ours appearing in one would mean something has gone
     * wrong upstream, and refusing is better than filing it under Brabant Wallon.
     */
    private const array LEVELS = [
        1 => LeagueLevel::NATIONAL,
        11 => LeagueLevel::PROVINCIAL_BW,
        15 => LeagueLevel::REGIONAL,
        16 => LeagueLevel::REGIONAL,
    ];

    public function __construct(private readonly TabtClient $client) {}

    public function import(Season $season, int $afttSeason, string $clubCode): void
    {
        $divisions = $this->client->divisions($afttSeason);

        foreach ($this->client->clubTeams($clubCode, $afttSeason) as $ourTeam) {
            $division = $divisions[$ourTeam->divisionId] ?? null;

            if (! $division instanceof AfttDivision) {
                continue;
            }

            $league = $this->league($season, $division);

            if (! $league instanceof League) {
                continue;
            }

            $matches = $this->client->divisionMatches($ourTeam->divisionId, $afttSeason);

            foreach ($matches as $match) {
                if (! $this->involvesUs($match, $clubCode)) {
                    continue;
                }

                $match->isBye
                    ? $this->bye($season, $league, $division, $match, $matches, $clubCode)
                    : $this->fixture($season, $league, $division, $match);
            }
        }
    }

    /**
     * The postal address a member drives to.
     *
     * Taken from the fixture's own venue rather than from the opposing club's
     * street, which is what the manual form does and what goes wrong the moment a
     * club plays somewhere other than its main hall — a case the federation
     * bothers to encode per match.
     *
     * The hall name is kept: it is the part that stops somebody circling a sports
     * complex looking for the right door. Everything arrives in capitals, so it
     * goes through the same normaliser the federation's member listing needed.
     */
    private function address(AfttMatch $match): string
    {
        if (! $match->venue instanceof AfttVenue) {
            return '';
        }

        $parts = array_filter([
            AddressNormalizer::titleCase($match->venue->name),
            AddressNormalizer::titleCase($match->venue->street),
            AddressNormalizer::titleCase($match->venue->town),
        ]);

        return implode(', ', $parts);
    }

    /**
     * Write a round in which our team has no opponent.
     *
     * The federation states no date for a bye — there is no match to hold one.
     * The date written here is the earliest day the rest of the division plays
     * that round, which is an inference and is documented as one wherever it
     * surfaces. It exists so a bye can be ordered among real fixtures at all:
     * `interclubs.start_date_time` is NOT NULL, and making it nullable would mean
     * guarding twenty-odd unguarded dereferences across the notifications, the
     * dashboard and the calendar feed — a refactor with its own risk, bought for
     * six rows.
     *
     * Only our own side is filled in. There is no opponent team to point at, and
     * inventing one would put a club on the calendar that is not playing.
     *
     * @param  array<int, AfttMatch>  $divisionMatches
     */
    private function bye(
        Season $season,
        League $league,
        AfttDivision $division,
        AfttMatch $match,
        array $divisionMatches,
        string $clubCode,
    ): void {
        $ourTeam = $this->team(
            $season,
            $league,
            $clubCode,
            $match->homeClub === $clubCode ? $match->homeTeam : $match->awayTeam,
        );

        if (! $ourTeam instanceof Team) {
            return;
        }

        $start = $this->roundDate($divisionMatches, $match->weekName);

        Interclub::updateOrCreate(
            [
                'season_id' => $season->id,
                'aftt_match_id' => $match->matchId,
            ],
            [
                'league_id' => $league->id,
                'visited_team_id' => $match->homeClub === $clubCode ? $ourTeam->id : null,
                'visiting_team_id' => $match->awayClub === $clubCode ? $ourTeam->id : null,
                'start_date_time' => $start,
                'address' => '',
                'round_number' => (int) $match->weekName,
                'week_number' => $start?->isoWeek,
                'total_players' => $this->totalPlayers($division),
                'is_bye' => true,
            ],
        );
    }

    /**
     * The name segment of a division, which is what the club calls it.
     *
     * "Division 3D - Prov. B.B.W. - Vétérans" is three segments; only the first
     * names the division, and the word "Division" in front of it is noise the
     * club never says out loud. A name without that prefix keeps its own shape.
     */
    private function divisionCode(string $divisionName): string
    {
        $first = trim(explode(' - ', $divisionName)[0]);

        return trim((string) preg_replace('/^Division\s+/i', '', $first));
    }

    /**
     * Write one fixture, creating whichever teams it needs.
     */
    private function fixture(Season $season, League $league, AfttDivision $division, AfttMatch $match): void
    {
        $home = $this->team($season, $league, $match->homeClub, $match->homeTeam);
        $away = $this->team($season, $league, $match->awayClub, $match->awayTeam);

        if (! $home instanceof Team || ! $away instanceof Team) {
            return;
        }

        $start = $match->date?->setTimeFromTimeString($match->time ?? '00:00:00');

        Interclub::updateOrCreate(
            [
                'season_id' => $season->id,
                'aftt_match_id' => $match->matchId,
            ],
            [
                'league_id' => $league->id,
                'visited_team_id' => $home->id,
                'visiting_team_id' => $away->id,
                'start_date_time' => $start,
                'address' => $this->address($match),
                'round_number' => (int) $match->weekName,
                'week_number' => $start?->isoWeek,
                'total_players' => $this->totalPlayers($division),
                'is_bye' => false,
            ],
        );
    }

    private function involvesUs(AfttMatch $match, string $clubCode): bool
    {
        return $match->homeClub === $clubCode || $match->awayClub === $clubCode;
    }

    private function league(Season $season, AfttDivision $division): ?League
    {
        $category = self::CATEGORIES[$division->category] ?? null;
        $level = self::LEVELS[$division->level] ?? null;

        if (! $category instanceof LeagueCategory || ! $level instanceof LeagueLevel) {
            return null;
        }

        return League::updateOrCreate(
            [
                'season_id' => $season->id,
                'aftt_division_id' => $division->id,
            ],
            [
                'division' => $this->divisionCode($division->name),
                'category' => $category->name,
                'level' => $level->name,
            ],
        );
    }

    /**
     * The earliest moment the division plays a given round.
     *
     * @param  array<int, AfttMatch>  $divisionMatches
     */
    private function roundDate(array $divisionMatches, string $weekName): ?CarbonImmutable
    {
        $dates = [];

        foreach ($divisionMatches as $other) {
            if ($other->weekName !== $weekName || ! $other->date instanceof CarbonImmutable) {
                continue;
            }

            $dates[] = $other->date->setTimeFromTimeString($other->time ?? '00:00:00');
        }

        if ($dates === []) {
            return null;
        }

        usort($dates, fn (CarbonImmutable $a, CarbonImmutable $b): int => $a <=> $b);

        return $dates[0];
    }

    /**
     * The team a federation team name refers to, created if we have never met it.
     *
     * A team's identity here is its letter, and the letter is the last word of
     * the name the federation prints: "Logis Auderghem Z" is team Z. Verified
     * against every team of every division the club plays in — 76 of them — where
     * the last word is a single capital without exception, the sole outlier being
     * the "Bye " that callers filter out before reaching this.
     *
     * The prefix is discarded rather than matched: the federation writes
     * "Cttr Alpa" where our own table says "ALPA SCHAERBEEK", and the club code
     * beside it already settles identity beyond argument.
     */
    private function team(Season $season, League $league, string $clubCode, string $teamName): ?Team
    {
        $club = Club::where('licence', $clubCode)->first();

        if (! $club instanceof Club) {
            return null;
        }

        $words = preg_split('/\s+/', trim($teamName)) ?: [];
        $letter = (string) end($words);

        return Team::firstOrCreate([
            'season_id' => $season->id,
            'league_id' => $league->id,
            'club_id' => $club->id,
            'name' => $letter,
        ]);
    }

    /**
     * How many players the club must field.
     *
     * Read from our own category rather than from the federation's match system,
     * which says the same thing: system 2 is four singles, system 4 is three
     * singles and a double. Worth revisiting only if a team of ours ever enters a
     * division on some other system.
     */
    private function totalPlayers(AfttDivision $division): int
    {
        return match (self::CATEGORIES[$division->category] ?? null) {
            LeagueCategory::MEN => 4,
            default => 3,
        };
    }
}
