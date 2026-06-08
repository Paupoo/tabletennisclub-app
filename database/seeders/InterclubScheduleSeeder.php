<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use Carbon\Carbon;
use Database\Seeders\Data\InterclubSeasonData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the 2025-2026 interclub calendar structure:
 *   1. season + leagues (divisions)
 *   2. our teams + opponent clubs/teams
 *   3. Interclub fixtures (NO scores — those are applied by InterclubResultsSeeder)
 *
 * The InterclubObserver creates the matching (empty) InterclubResult rows.
 */
class InterclubScheduleSeeder extends Seeder
{
    private const MATCH_TIME = '19:45:00';

    private Club $ourClub;

    private Season $season;

    public function run(): void
    {
        Season::query()->update(['is_active' => false]);
        Cache::forget('season.current');

        $this->season = Season::updateOrCreate(
            ['name' => InterclubSeasonData::SEASON_NAME],
            [
                'start_at' => InterclubSeasonData::SEASON_START,
                'end_at' => InterclubSeasonData::SEASON_END,
                'is_active' => true,
            ]
        );

        $this->ourClub = Club::own();

        foreach (InterclubSeasonData::teams() as $teamData) {
            $this->seedTeam($teamData);
        }
    }

    private function league(string $division, LeagueCategory $category): League
    {
        return League::firstOrCreate(
            ['division' => $division, 'season_id' => $this->season->id, 'category' => $category->name],
            ['level' => LeagueLevel::PROVINCIAL_BW->name]
        );
    }

    private function opponentClub(string $clubName): Club
    {
        return Club::firstOrCreate(
            ['name' => $clubName],
            [
                'licence' => 'S-' . substr(md5($clubName), 0, 6),
                'is_active' => true,
            ]
        );
    }

    private function opponentTeam(string $opponentFullName, League $league): Team
    {
        // "Arc En Ciel F" → clubName="Arc En Ciel", letter="F"
        // "Logis Auderghem 2" → clubName="Logis Auderghem", letter="2"
        $parts = explode(' ', $opponentFullName);
        $teamLetter = array_pop($parts);
        $clubName = implode(' ', $parts);

        $club = $this->opponentClub($clubName);

        return Team::firstOrCreate([
            'name' => $teamLetter,
            'league_id' => $league->id,
            'season_id' => $this->season->id,
            'club_id' => $club->id,
        ]);
    }

    private function ourTeam(string $name, League $league, string $position): Team
    {
        $team = Team::firstOrCreate(
            ['name' => $name, 'season_id' => $this->season->id, 'league_id' => $league->id],
            ['club_id' => $this->ourClub->id, 'final_position' => $position]
        );

        $team->update(['final_position' => $position]);

        return $team;
    }

    /**
     * @param  array{category: LeagueCategory, team: string, division: string, position: string, matches: array<int, array<string, mixed>>}  $teamData
     */
    private function seedTeam(array $teamData): void
    {
        $league = $this->league($teamData['division'], $teamData['category']);
        $ourTeam = $this->ourTeam($teamData['team'], $league, $teamData['position']);
        $players = $teamData['category'] === LeagueCategory::MEN ? 4 : 3;

        foreach ($teamData['matches'] as $match) {
            $startDateTime = $match['date'] . ' ' . self::MATCH_TIME;
            $weekNumber = Carbon::parse($match['date'])->isoWeek();

            if ($match['bye'] ?? false) {
                Interclub::updateOrCreate(
                    [
                        'visited_team_id' => $ourTeam->id,
                        'visiting_team_id' => null,
                        'season_id' => $this->season->id,
                        'league_id' => $league->id,
                        'start_date_time' => $startDateTime,
                    ],
                    [
                        'week_number' => $weekNumber,
                        'total_players' => $players,
                        'is_bye' => true,
                        'address' => '',
                    ]
                );

                continue;
            }

            $opponentTeam = $this->opponentTeam($match['opponent'], $league);

            Interclub::updateOrCreate(
                [
                    'visited_team_id' => $match['home'] ? $ourTeam->id : $opponentTeam->id,
                    'visiting_team_id' => $match['home'] ? $opponentTeam->id : $ourTeam->id,
                    'season_id' => $this->season->id,
                    'league_id' => $league->id,
                ],
                [
                    'start_date_time' => $startDateTime,
                    'week_number' => $weekNumber,
                    'total_players' => $players,
                    'is_bye' => false,
                    'address' => '',
                ]
            );
        }
    }
}
