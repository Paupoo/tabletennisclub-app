<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\AfttClub;
use App\Data\Interclub\AfttDivision;
use App\Data\Interclub\AfttMatch;
use App\Data\Interclub\AfttMatchSheet;
use App\Data\Interclub\AfttRanking;
use App\Data\Interclub\AfttSeasons;
use App\Data\Interclub\AfttSheetPlayer;
use App\Data\Interclub\AfttSheetResult;
use App\Data\Interclub\AfttTeam;
use App\Data\Interclub\AfttVenue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Speaks to TabT, the federation's competition database, and returns nothing but
 * value objects.
 *
 * Deliberately free of any knowledge of our tables: this class turns XML into
 * DTOs, {@see AfttCalendarImporter} turns DTOs into rows. The seam is what lets
 * the mapping rules be tested against a committed fixture with no network, and
 * the parsing be tested with no database.
 *
 * Raw XML over the HTTP client rather than SoapClient: `ext-soap` is not
 * installed here and asking a shared host to add it is a deployment risk taken
 * for nothing. The envelope is four lines.
 */
class TabtClient
{
    /**
     * How TabT names the absent opponent. Trimmed on the way in, because the
     * federation writes it with a trailing space.
     */
    private const string BYE = 'Bye';

    /**
     * TabT's own namespace, unchanged since the API was published.
     */
    private const string NAMESPACE = 'http://api.frenoy.net/TabTAPI';

    /**
     * One club and its first hall, or null when the federation has no such club.
     *
     * Only ever called for a club code met in a fixture and absent from our own
     * table — which, the roster being complete, means a new opponent next season.
     */
    public function club(string $licence, int $season): ?AfttClub
    {
        $body = $this->call('GetClubs', 'GetClubs', [
            'Club' => $licence,
            'Season' => $season,
        ]);

        $entries = $body->xpath('//t:ClubEntries');

        if ($entries === [] || $entries === null) {
            return null;
        }

        $entries[0]->registerXPathNamespace('t', self::NAMESPACE);

        return new AfttClub(
            licence: $this->text($entries[0], 'UniqueIndex') ?? $licence,
            name: $this->text($entries[0], 'Name') ?? '',
            longName: $this->text($entries[0], 'LongName') ?? '',
            venue: $this->venue($entries[0], 'VenueEntries'),
        );
    }

    /**
     * The teams the club fielded this season, each with the division it plays in.
     *
     * @return array<int, AfttTeam>
     */
    public function clubTeams(string $club, int $season): array
    {
        $body = $this->call('GetClubTeams', 'GetClubTeamsRequest', [
            'Club' => $club,
            'Season' => $season,
        ]);

        $teams = [];

        foreach ($body->xpath('//t:TeamEntries') ?: [] as $entry) {
            $entry->registerXPathNamespace('t', self::NAMESPACE);

            $teams[] = new AfttTeam(
                letter: $this->text($entry, 'Team') ?? '',
                divisionId: (int) $this->text($entry, 'DivisionId'),
                divisionName: $this->text($entry, 'DivisionName') ?? '',
                divisionCategory: (int) $this->text($entry, 'DivisionCategory'),
            );
        }

        return $teams;
    }

    /**
     * Every fixture of one division, ours and everybody else's.
     *
     * The whole division rather than just our club's matches: a bye carries no
     * date, and the only honest way to place it in the season is the day its
     * division plays that round — which is knowable only from the fixtures we
     * would otherwise have filtered out.
     *
     * @return array<int, AfttMatch>
     */
    public function divisionMatches(int $divisionId, int $season): array
    {
        $body = $this->call('GetMatches', 'GetMatchesRequest', [
            'DivisionId' => $divisionId,
            'Season' => $season,
            'ShowDivisionName' => 'yes',
        ]);

        $matches = [];

        foreach ($body->xpath('//t:TeamMatchesEntries') ?: [] as $entry) {
            $entry->registerXPathNamespace('t', self::NAMESPACE);

            $date = $this->text($entry, 'Date');
            $homeTeam = $this->text($entry, 'HomeTeam') ?? '';
            $awayTeam = $this->text($entry, 'AwayTeam') ?? '';

            $matches[] = new AfttMatch(
                matchId: $this->text($entry, 'MatchId') ?? '',
                weekName: $this->text($entry, 'WeekName') ?? '',
                date: $date === null || $date === '' ? null : CarbonImmutable::parse($date),
                time: $this->text($entry, 'Time') ?: null,
                homeClub: $this->text($entry, 'HomeClub') ?? '',
                homeTeam: $homeTeam,
                awayClub: $this->text($entry, 'AwayClub') ?? '',
                awayTeam: $awayTeam,
                divisionId: (int) $this->text($entry, 'DivisionId'),
                divisionName: $this->text($entry, 'DivisionName') ?? '',
                divisionCategory: (int) $this->text($entry, 'DivisionCategory'),
                venue: $this->venue($entry),
                isBye: $homeTeam === self::BYE || $awayTeam === self::BYE,
            );
        }

        return $matches;
    }

    /**
     * The match sheets of one division: who played, against whom, and how it
     * ended, line by line.
     *
     * A separate call from {@see divisionMatches()} rather than a flag on it.
     * `WithDetails` multiplies the payload by roughly eight — 109 kB became
     * 896 kB on the division measured — and the calendar import, which runs over
     * every division of the season and wants nothing but dates and venues, has
     * no reason to pay for it.
     *
     * Returns every fixture, encoded or not; `detailsCreated` is what separates
     * a tie that has been played and reported from one that merely has a date.
     *
     * @return array<int, AfttMatchSheet>
     */
    public function divisionMatchSheets(int $divisionId, int $season): array
    {
        $body = $this->call('GetMatches', 'GetMatchesRequest', [
            'DivisionId' => $divisionId,
            'Season' => $season,
            'WithDetails' => 1,
        ]);

        $sheets = [];

        foreach ($body->xpath('//t:TeamMatchesEntries') ?: [] as $entry) {
            $entry->registerXPathNamespace('t', self::NAMESPACE);

            $details = $entry->xpath('./t:MatchDetails');
            $detail = $details === [] || $details === null ? null : $details[0];
            $detail?->registerXPathNamespace('t', self::NAMESPACE);

            $sheets[] = new AfttMatchSheet(
                matchId: $this->text($entry, 'MatchId') ?? '',
                detailsCreated: $detail !== null && $this->text($detail, 'DetailsCreated') === 'true',
                score: $this->text($entry, 'Score') ?: null,
                matchSystem: $detail === null ? null : ((int) $this->text($detail, 'MatchSystem') ?: null),
                isHomeForfeited: $this->text($entry, 'IsHomeForfeited') === 'true',
                isAwayForfeited: $this->text($entry, 'IsAwayForfeited') === 'true',
                homePlayers: $detail === null ? [] : $this->sheetPlayers($detail, 'HomePlayers'),
                awayPlayers: $detail === null ? [] : $this->sheetPlayers($detail, 'AwayPlayers'),
                results: $detail === null ? [] : $this->sheetResults($detail),
            );
        }

        return $sheets;
    }

    /**
     * The final table of one division.
     *
     * Where a team finished is a fact the federation keeps and the club has to
     * type in by hand — for the running season a captain will, for the ten
     * behind us nobody ever will. Six kilobytes a division, so it costs
     * nothing to ask.
     *
     * @return array<int, AfttRanking>
     */
    public function divisionRanking(int $divisionId, int $season): array
    {
        $body = $this->call('GetDivisionRanking', 'GetDivisionRankingRequest', [
            'DivisionId' => $divisionId,
            'Season' => $season,
        ]);

        $entries = [];

        foreach ($body->xpath('//t:RankingEntries') ?: [] as $entry) {
            $entry->registerXPathNamespace('t', self::NAMESPACE);

            $entries[] = new AfttRanking(
                position: (int) $this->text($entry, 'Position'),
                team: $this->text($entry, 'Team') ?? '',
                teamClub: $this->text($entry, 'TeamClub') ?? '',
                gamesPlayed: (int) $this->text($entry, 'GamesPlayed'),
                gamesWon: (int) $this->text($entry, 'GamesWon'),
                gamesLost: (int) $this->text($entry, 'GamesLost'),
                gamesDraw: (int) $this->text($entry, 'GamesDraw'),
                points: (int) $this->text($entry, 'Points'),
            );
        }

        return $entries;
    }

    /**
     * Every division of the season, keyed by its federation id.
     *
     * Fetched whole rather than one by one: the federation offers no per-division
     * lookup, the whole list is one call, and we need the level of each division
     * our teams play in.
     *
     * @return array<int, AfttDivision>
     */
    public function divisions(int $season): array
    {
        $body = $this->call('GetDivisions', 'GetDivisions', [
            'Season' => $season,
            'ShowDivisionName' => 'yes',
        ]);

        $divisions = [];

        foreach ($body->xpath('//t:DivisionEntries') ?: [] as $entry) {
            $entry->registerXPathNamespace('t', self::NAMESPACE);

            $id = (int) $this->text($entry, 'DivisionId');

            $divisions[$id] = new AfttDivision(
                id: $id,
                name: $this->text($entry, 'DivisionName') ?? '',
                category: (int) $this->text($entry, 'DivisionCategory'),
                level: (int) $this->text($entry, 'Level'),
            );
        }

        return $divisions;
    }

    public function seasons(): AfttSeasons
    {
        $body = $this->call('GetSeasons', 'GetSeasonsRequest');

        $all = [];

        foreach ($body->xpath('//t:SeasonEntries') ?: [] as $entry) {
            $entry->registerXPathNamespace('t', self::NAMESPACE);
            $all[(int) $this->text($entry, 'Season')] = $this->text($entry, 'Name') ?? '';
        }

        return new AfttSeasons(
            currentSeason: (int) $this->find($body, 'CurrentSeason'),
            currentSeasonName: $this->find($body, 'CurrentSeasonName') ?? '',
            all: $all,
        );
    }

    /**
     * Post one SOAP envelope and hand back the response body, namespace already
     * registered so callers can path into it.
     *
     * The request element name is passed in rather than derived from the action:
     * TabT's WSDL is inconsistent about it — `GetMatches` expects
     * `GetMatchesRequest`, while `GetClubs` expects plain `GetClubs`, and
     * guessing wrong earns a `Procedure not present` fault, not a helpful error.
     *
     * @param  array<string, scalar>  $arguments
     */
    private function call(string $action, string $element, array $arguments = []): SimpleXMLElement
    {
        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '"' . $action . '"',
        ])
            ->withBody($this->envelope($element, $arguments), 'text/xml')
            ->post(config('aftt.base_url'));

        $xml = new SimpleXMLElement($response->body());
        $xml->registerXPathNamespace('t', self::NAMESPACE);

        $fault = $xml->xpath('//faultstring');

        if ($fault !== [] && $fault !== null) {
            throw new RuntimeException('TabT refused ' . $action . ': ' . $fault[0]);
        }

        return $xml;
    }

    /**
     * @param  array<string, scalar>  $arguments
     */
    private function envelope(string $element, array $arguments): string
    {
        $inner = '';

        foreach ($arguments as $name => $value) {
            $inner .= '<t:' . $name . '>' . htmlspecialchars((string) $value, ENT_XML1) . '</t:' . $name . '>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:t="' . self::NAMESPACE . '">'
            . '<soap:Body><t:' . $element . '>' . $inner . '</t:' . $element . '></soap:Body>'
            . '</soap:Envelope>';
    }

    private function find(SimpleXMLElement $xml, string $name): ?string
    {
        return $this->firstText($xml, './/t:' . $name);
    }

    private function firstText(SimpleXMLElement $xml, string $path): ?string
    {
        $found = $xml->xpath($path);

        if ($found === [] || $found === null) {
            return null;
        }

        return trim((string) $found[0]);
    }

    private function licenceOrNull(?string $value): ?string
    {
        return in_array($value, [null, '', '0'], true) ? null : $value;
    }

    /**
     * First matching element anywhere below this one, as a trimmed string.
     *
     * For reaching into a response envelope, never for reading an entry: a
     * descendant search from a match entry would happily return the venue's name
     * when asked for the team's.
     */
    /**
     * The players of one side, in the order the sheet lists them.
     *
     * @return array<int, AfttSheetPlayer>
     */
    private function sheetPlayers(SimpleXMLElement $detail, string $side): array
    {
        $blocks = $detail->xpath('./t:' . $side);

        if ($blocks === [] || $blocks === null) {
            return [];
        }

        $blocks[0]->registerXPathNamespace('t', self::NAMESPACE);
        $players = [];

        foreach ($blocks[0]->xpath('./t:Players') ?: [] as $node) {
            $node->registerXPathNamespace('t', self::NAMESPACE);

            $index = $this->text($node, 'UniqueIndex');

            if (in_array($index, [null, '', '0'], true)) {
                continue;
            }

            $victories = $this->text($node, 'VictoryCount');

            $players[] = new AfttSheetPlayer(
                position: (int) $this->text($node, 'Position'),
                uniqueIndex: $index,
                firstName: $this->text($node, 'FirstName') ?? '',
                lastName: $this->text($node, 'LastName') ?? '',
                ranking: $this->text($node, 'Ranking') ?: null,
                victoryCount: $victories === null || $victories === '' ? null : (int) $victories,
            );
        }

        return $players;
    }

    /**
     * The individual matches of one tie.
     *
     * A player index of `0` means "nobody named here", which is how the
     * federation writes the double. It becomes null rather than "0" so that no
     * caller can ever join it against a licence.
     *
     * @return array<int, AfttSheetResult>
     */
    private function sheetResults(SimpleXMLElement $detail): array
    {
        $results = [];

        foreach ($detail->xpath('./t:IndividualMatchResults') ?: [] as $node) {
            $node->registerXPathNamespace('t', self::NAMESPACE);

            $home = $this->text($node, 'HomeSetCount');
            $away = $this->text($node, 'AwaySetCount');

            $results[] = new AfttSheetResult(
                position: (int) $this->text($node, 'Position'),
                homeUniqueIndex: $this->licenceOrNull($this->text($node, 'HomePlayerUniqueIndex')),
                awayUniqueIndex: $this->licenceOrNull($this->text($node, 'AwayPlayerUniqueIndex')),
                homeSetCount: $home === null || $home === '' ? null : (int) $home,
                awaySetCount: $away === null || $away === '' ? null : (int) $away,
                isHomeForfeited: $this->text($node, 'IsHomeForfeited') === 'true',
                isAwayForfeited: $this->text($node, 'IsAwayForfeited') === 'true',
            );
        }

        return $results;
    }

    /**
     * Direct child of this element, as a trimmed string.
     *
     * Deliberately not a descendant search — see {@see find()}. TabT nests a
     * `VenueEntry` inside a match entry and both carry a `Name`.
     */
    private function text(SimpleXMLElement $xml, string $name): ?string
    {
        return $this->firstText($xml, './t:' . $name);
    }

    private function venue(SimpleXMLElement $entry, string $element = 'VenueEntry'): ?AfttVenue
    {
        $venue = $entry->xpath('./t:' . $element);

        if ($venue === [] || $venue === null) {
            return null;
        }

        $venue[0]->registerXPathNamespace('t', self::NAMESPACE);

        return new AfttVenue(
            name: $this->text($venue[0], 'Name') ?? '',
            street: $this->text($venue[0], 'Street') ?? '',
            town: $this->text($venue[0], 'Town') ?? '',
        );
    }
}
