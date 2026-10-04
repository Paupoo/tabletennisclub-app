<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\AfttTournamentResult;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\OfficialTournamentMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Exceptions\TabtQuotaExceeded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * Copies the club members' official tournament matches from the federation.
 *
 * The federation gives these lines no identifier, so nothing is matched line
 * by line: for each player the list TabT returns *is* their season, and it
 * replaces whatever we held. A score the federation corrects, or a match it
 * strikes off, is picked up without anyone having to notice.
 *
 * What it never does is empty a player. A licence we hold lines for that comes
 * back with none is left alone and named in the report — a federation that
 * served half a list must not read here as a player who never played.
 */
class OfficialTournamentImporter
{
    /**
     * Long enough for a spent quota to drain: refused at 14:37, back to zero
     * by 14:44 when it was measured.
     */
    public const int QUOTA_WAIT_MINUTES = 8;

    /**
     * @var array{unknown_licences: array<string, string>, emptied_licences: array<string, string>}
     */
    private array $report = [
        'emptied_licences' => [],
        'unknown_licences' => [],
    ];

    /** @var array<string, int> */
    private array $tally = [
        'matches_written' => 0,
        'players' => 0,
    ];

    /**
     * Replace this season's tournament matches with the federation's.
     *
     * @return array{tally: array<string, int>, report: array{unknown_licences: array<string, string>, emptied_licences: array<string, string>}}
     */
    public function import(Season $season, int $afttSeason, TabtClient $client): array
    {
        $ourClub = (string) Club::query()->where('is_own_club', true)->value('licence');

        $byPlayer = collect($this->fetch($client, $ourClub, $afttSeason))
            ->groupBy(fn (AfttTournamentResult $result): string => $result->playerLicence);

        $members = User::query()
            ->whereIn('licence', $byPlayer->keys())
            ->pluck('id', 'licence');

        DB::transaction(function () use ($season, $byPlayer, $members): void {
            foreach ($byPlayer as $licence => $results) {
                $this->replacePlayer($season, (string) $licence, $results->all(), $members[$licence] ?? null);
            }
        });

        $this->reportEmptied($season, $byPlayer->keys()->map(fn ($licence): string => (string) $licence)->all());
        $this->linkMembers();

        return ['tally' => $this->tally, 'report' => $this->report];
    }

    /**
     * The federation's list, asked for a second time if the quota was spent.
     *
     * The allowance drains in a few minutes, and this call alone costs more
     * than it — so a refusal usually means another import just ran from the
     * same address. One wait, one retry; a second refusal is left to fail.
     *
     * @return array<int, AfttTournamentResult>
     */
    private function fetch(TabtClient $client, string $club, int $afttSeason): array
    {
        try {
            return $client->clubTournamentResults($club, $afttSeason);
        } catch (TabtQuotaExceeded) {
            Sleep::for(self::QUOTA_WAIT_MINUTES)->minutes();

            return $client->clubTournamentResults($club, $afttSeason);
        }
    }

    /**
     * Hand each member the lines filed under their licence while nobody held it.
     *
     * Every season, not just the one imported: a member created or corrected
     * today owns matches from years the nightly run will never fetch again.
     */
    private function linkMembers(): void
    {
        OfficialTournamentMatch::query()
            ->whereNull('user_id')
            ->whereIn('player_licence', User::query()->whereNotNull('licence')->select('licence'))
            ->get(['id', 'player_licence'])
            ->groupBy('player_licence')
            ->each(function ($lines, int|string $licence): void {
                OfficialTournamentMatch::query()
                    ->whereIn('id', $lines->pluck('id'))
                    ->update(['user_id' => User::query()->where('licence', (string) $licence)->value('id')]);
            });
    }

    /**
     * @param  array<int, AfttTournamentResult>  $results
     */
    private function replacePlayer(Season $season, string $licence, array $results, ?int $userId): void
    {
        OfficialTournamentMatch::query()
            ->where('season_id', $season->id)
            ->where('player_licence', $licence)
            ->delete();

        $now = now();

        OfficialTournamentMatch::query()->insert(array_map(fn (AfttTournamentResult $result): array => [
            'created_at' => $now,
            'opponent_club' => $result->opponentClub,
            'opponent_licence' => $result->opponentLicence,
            'opponent_name' => $result->opponentName,
            'opponent_ranking' => $result->opponentRanking,
            'our_sets' => $result->ourSets,
            'played_on' => $result->playedOn->toDateString(),
            'player_licence' => $licence,
            'player_name' => $result->playerName,
            'player_ranking' => $result->playerRanking,
            'season_id' => $season->id,
            'serie_name' => $result->serieName,
            'their_sets' => $result->theirSets,
            'tournament_name' => $result->tournamentName,
            'updated_at' => $now,
            'user_id' => $userId,
            'we_won' => $result->won,
        ], $results));

        $this->tally['players']++;
        $this->tally['matches_written'] += count($results);

        if ($userId === null) {
            $this->report['unknown_licences'][$licence] = $results[0]->playerName;
        }
    }

    /**
     * Name every player we hold lines for whom the federation returned none.
     *
     * @param  array<int, string>  $returned
     */
    private function reportEmptied(Season $season, array $returned): void
    {
        OfficialTournamentMatch::query()
            ->where('season_id', $season->id)
            ->whereNotIn('player_licence', $returned)
            ->select('player_licence', 'player_name')
            ->distinct()
            ->orderBy('player_licence')
            ->get()
            ->each(function (OfficialTournamentMatch $line): void {
                $this->report['emptied_licences'][$line->player_licence] = $line->player_name;
            });
    }
}
