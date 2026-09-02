<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\AfttClub;
use App\Data\Interclub\AfttDivision;
use App\Data\Interclub\AfttMatch;
use App\Data\Interclub\AfttVenue;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubImport;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use App\Domains\Shared\Support\AddressNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

    /**
     * What this run did, gathered as it goes and written to the audit row.
     *
     * @var array<string, array<int, string>>
     */
    private array $changes = [];

    /**
     * Federation identifiers seen in this run, which is what makes anything else
     * carrying one an orphan.
     *
     * @var array<int, string>
     */
    private array $seen = [];

    public function __construct(private readonly TabtClient $client) {}

    public function import(Season $season, int $afttSeason, string $clubCode, bool $fresh = false): InterclubImport
    {
        $this->seen = [];
        $this->changes = [];

        $plan = $this->fetch($afttSeason, $clubCode);

        return DB::transaction(function () use ($season, $afttSeason, $clubCode, $fresh, $plan): InterclubImport {
            if ($fresh) {
                $this->wipe($season);
            }

            foreach ($plan as ['division' => $division, 'matches' => $matches]) {
                $league = $this->league($season, $division);

                if (! $league instanceof League) {
                    continue;
                }

                foreach ($matches as $match) {
                    if (! $this->involvesUs($match, $clubCode)) {
                        continue;
                    }

                    $this->seen[] = $match->matchId;

                    $match->isBye
                        ? $this->bye($season, $league, $division, $match, $matches, $clubCode, $afttSeason)
                        : $this->fixture($season, $league, $division, $match, $afttSeason);
                }
            }

            $this->reconcileOrphans($season);

            return InterclubImport::create([
                'season_id' => $season->id,
                'is_fresh' => $fresh,
                'created_count' => count($this->changes['created'] ?? []),
                'updated_count' => count($this->changes['moved'] ?? []),
                'deleted_count' => count($this->changes['deleted_orphans'] ?? []),
                'unchanged_count' => max(0, count($this->seen)
                    - count($this->changes['created'] ?? [])
                    - count($this->changes['moved'] ?? [])),
                'skipped_count' => count($this->changes['refused_divisions'] ?? []),
                'changes' => $this->changes,
            ]);
        });
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
        int $afttSeason,
    ): void {
        $ourTeam = $this->team(
            $season,
            $league,
            $clubCode,
            $match->homeClub === $clubCode ? $match->homeTeam : $match->awayTeam,
            $afttSeason,
        );

        if (! $ourTeam instanceof Team) {
            return;
        }

        $start = $this->roundDate($divisionMatches, $match->weekName);

        $this->record($match->matchId, $season, $start, '');

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
     * Whether this division fits the categories and levels the club models.
     */
    private function canModel(AfttDivision $division): bool
    {
        return isset(self::CATEGORIES[$division->category])
            && isset(self::LEVELS[$division->level]);
    }

    /**
     * Whether anybody at the club has already acted on this fixture.
     *
     * The linked InterclubResult does not count on its own: the observer creates
     * one for every fixture the moment it is written, so its mere existence says
     * nothing. A score or a result in it does.
     */
    private function carriesMemberData(Interclub $interclub): bool
    {
        if ($interclub->users()->exists()) {
            return true;
        }

        return InterclubResult::where('interclub_id', $interclub->id)
            ->where(fn ($query) => $query->whereNotNull('result')->orWhereNotNull('score'))
            ->exists();
    }

    /**
     * The club behind a federation code, created if we have never met it.
     *
     * Identity is the licence, which holds the federation's own club index. The
     * roster already carries it for every opponent the club plays, so creating
     * one means a genuinely new opponent — a fixture list that changed over the
     * summer, not a duplicate of something typed by hand.
     *
     * What an existing club keeps is the point of this method. Its name is never
     * rewritten: the table is curated by people, with contact addresses, bank
     * details and coordinates the federation knows nothing about, and a nightly
     * run that renamed "MUPPETS T.T. AUDERGHEM" to "Muppet's TT Auderghem" would
     * be quietly undoing somebody's work. The postal address is filled only where
     * the club has none — the same fill-if-missing asymmetry the member listing
     * import settled on, for the same reason.
     */
    private function club(string $licence, int $afttSeason): ?Club
    {
        $club = Club::where('licence', $licence)->first();
        $known = $club instanceof Club;

        if ($known && $club->street !== null && $club->city_code !== null) {
            return $club;
        }

        $published = $this->client->club($licence, $afttSeason);

        if (! $published instanceof AfttClub) {
            return $club;
        }

        [$cityCode, $cityName] = $this->splitTown($published->venue?->town);

        if (! $known) {
            return Club::create([
                'licence' => $published->licence,
                'name' => $published->longName !== '' ? $published->longName : $published->name,
                'is_own_club' => false,
                'street' => AddressNormalizer::titleCase($published->venue?->street),
                'city_code' => $cityCode,
                'city_name' => $cityName,
            ]);
        }

        $club->fill(array_filter([
            'street' => $club->street ?? AddressNormalizer::titleCase($published->venue?->street),
            'city_code' => $club->city_code ?? $cityCode,
            'city_name' => $club->city_name ?? $cityName,
        ]))->save();

        return $club;
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
     * Ask the federation for everything this run needs, before touching a row.
     *
     * Separated from the writing on purpose. A season is rebuilt by deleting it
     * first, so a federation that times out on the sixth of nine divisions must
     * not be able to leave the club with a deleted calendar and nothing to put in
     * its place. Fetching first means the transaction opens only once every
     * answer is in hand.
     *
     * @return array<int, array{division: AfttDivision, matches: array<int, AfttMatch>}>
     */
    private function fetch(int $afttSeason, string $clubCode): array
    {
        $divisions = $this->client->divisions($afttSeason);
        $plan = [];

        foreach ($this->client->clubTeams($clubCode, $afttSeason) as $ourTeam) {
            $division = $divisions[$ourTeam->divisionId] ?? null;

            if (! $division instanceof AfttDivision) {
                continue;
            }

            // Refused here rather than after the fetch: there is no reason to ask
            // the federation for the fixtures of a division we will not write.
            if (! $this->canModel($division)) {
                $this->changes['refused_divisions'][] = $division->name;

                continue;
            }

            $plan[] = [
                'division' => $division,
                'matches' => $this->client->divisionMatches($ourTeam->divisionId, $afttSeason),
            ];
        }

        return $plan;
    }

    /**
     * Write one fixture, creating whichever teams it needs.
     */
    private function fixture(Season $season, League $league, AfttDivision $division, AfttMatch $match, int $afttSeason): void
    {
        $home = $this->team($season, $league, $match->homeClub, $match->homeTeam, $afttSeason);
        $away = $this->team($season, $league, $match->awayClub, $match->awayTeam, $afttSeason);

        if (! $home instanceof Team || ! $away instanceof Team) {
            return;
        }

        $start = $match->date?->setTimeFromTimeString($match->time ?? '00:00:00');

        $this->record($match->matchId, $season, $start, $this->address($match));

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
     * Deal with fixtures we once imported and the federation no longer lists.
     *
     * It happens for real: a team withdraws, the division is recomputed, and the
     * identifiers move with it. A fixture nobody has touched is simply dropped —
     * it was never seen by a member and leaving it would put a phantom match on
     * the calendar, which is worse than a gap. One that carries an availability,
     * a selection or a recorded result is kept and named in the report, because
     * that is the club's own data and no third party's change of mind should
     * silently delete it.
     *
     * Rows without a federation identifier are not orphans and are never
     * considered: they were typed by hand and this import has no claim on them.
     */
    private function reconcileOrphans(Season $season): void
    {
        $orphans = Interclub::where('season_id', $season->id)
            ->whereNotNull('aftt_match_id')
            ->whereNotIn('aftt_match_id', $this->seen === [] ? [''] : $this->seen)
            ->get();

        foreach ($orphans as $orphan) {
            if ($this->carriesMemberData($orphan)) {
                $this->changes['kept_orphans'][] = (string) $orphan->aftt_match_id;

                continue;
            }

            $this->changes['deleted_orphans'][] = (string) $orphan->aftt_match_id;
            $orphan->delete();
        }
    }

    /**
     * Note whether this fixture is new to us, and whether it has moved.
     *
     * Read before the write, because afterwards there is nothing left to compare
     * against. A move is a change of moment or of hall — the two things a member
     * would have to be told about, and the reason the list is kept rather than
     * just counted.
     */
    private function record(string $matchId, Season $season, ?CarbonImmutable $start, string $address): void
    {
        $existing = Interclub::where('season_id', $season->id)
            ->where('aftt_match_id', $matchId)
            ->first();

        if (! $existing instanceof Interclub) {
            $this->changes['created'][] = $matchId;

            return;
        }

        $movedInTime = $existing->start_date_time?->toDateTimeString() !== $start?->toDateTimeString();

        if ($movedInTime || $existing->address !== $address) {
            $this->changes['moved'][] = $matchId;
        }
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
     * Split "1160 BRUXELLES" into its postal code and its locality.
     *
     * A Belgian postal code is four digits, which is what makes the split safe to
     * do on position: anything else is the locality's own first word.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitTown(?string $town): array
    {
        if ($town === null || trim($town) === '') {
            return [null, null];
        }

        if (preg_match('/^\s*(\d{4})\s+(.+)$/', $town, $matches) !== 1) {
            return [null, AddressNormalizer::titleCase($town)];
        }

        return [$matches[1], AddressNormalizer::titleCase($matches[2])];
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
    private function team(Season $season, League $league, string $clubCode, string $teamName, int $afttSeason): ?Team
    {
        $club = $this->club($clubCode, $afttSeason);

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

    /**
     * Empty one season of everything the federation is about to restate.
     *
     * Only ever reached through the command's --fresh, which refuses to run when
     * anything in the season carries member data. What goes with the teams is the
     * club's own work — captains and rosters — so this is deliberately not the
     * default and deliberately not silent.
     *
     * Scoped to the season, because last season's results are read all year.
     */
    private function wipe(Season $season): void
    {
        Interclub::where('season_id', $season->id)->get()->each->delete();
        Team::where('season_id', $season->id)->delete();
        League::where('season_id', $season->id)->delete();
    }
}
