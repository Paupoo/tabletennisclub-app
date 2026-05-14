<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\InterclubResult;
use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\MatchResult;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class InterclubResultsSeeder extends Seeder
{
    private Club $club;

    private Season $season;

    public function run(): void
    {
        Season::query()->update(['is_active' => false]);
        Cache::forget('season.current');

        $this->season = Season::updateOrCreate(
            ['name' => '2025-2026'],
            [
                'start_at' => '2025-09-01 00:00:00',
                'end_at' => '2026-06-30 00:00:00',
                'is_active' => true,
            ]
        );

        $this->club = Club::firstWhere('licence', config('app.club_licence'));

        $this->seedMen();
        $this->seedVeterans();
    }

    private function league(string $division, LeagueCategory $category): League
    {
        return League::firstOrCreate(
            ['division' => $division, 'season_id' => $this->season->id, 'category' => $category->name],
            ['level' => LeagueLevel::PROVINCIAL_BW->name]
        );
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: bool, 3: string|null, 4: InterclubResult}|null>  $matches
     */
    private function seedMatches(Team $team, array $matches): void
    {
        MatchResult::where('team_id', $team->id)
            ->where('season_id', $this->season->id)
            ->delete();

        foreach ($matches as $match) {
            if ($match === null) {
                MatchResult::create([
                    'team_id' => $team->id,
                    'season_id' => $this->season->id,
                    'is_bye' => true,
                ]);

                continue;
            }

            [$date, $opponent, $isHome, $score, $result] = $match;

            MatchResult::create([
                'team_id' => $team->id,
                'season_id' => $this->season->id,
                'match_date' => $date,
                'week_number' => Carbon::parse($date)->isoWeek(),
                'is_home' => $isHome,
                'opponent_name' => $opponent,
                'score' => $score,
                'result' => $result,
                'is_bye' => false,
            ]);
        }
    }

    private function seedMen(): void
    {
        $teamA = $this->team('A', $this->league('2C', LeagueCategory::MEN), '1ère place');
        $this->seedMatches($teamA, [
            ['2025-09-12', 'Arc En Ciel F',         true,  '15-1',  InterclubResult::WIN],
            ['2025-09-21', 'TT Zenith Brussels A',  false, '7-9',   InterclubResult::WIN],
            ['2025-09-26', "Braine l'Alleud I",     true,  '14-2',  InterclubResult::WIN],
            ['2025-10-10', 'Logis Auderghem I',     true,  '10-6',  InterclubResult::WIN],
            ['2025-10-18', 'Gremlins A',            false, '3-13',  InterclubResult::WIN],
            ['2025-11-07', 'Tourinnes A',           false, '9-7',   InterclubResult::LOSS],
            ['2025-11-14', 'La Hulpe-Rix. C',       false, '4-12',  InterclubResult::WIN],
            ['2025-11-21', 'Set-Jet Fleur Bleue D', true,  '13-3',  InterclubResult::WIN],
            ['2025-11-28', 'Eveil B',               true,  '16-0',  InterclubResult::WIN],
            ['2026-01-09', 'Arc En Ciel F',         false, null,    InterclubResult::WITHDRAWAL_OPPONENT],
            ['2026-01-16', 'TT Zenith Brussels A',  true,  '11-5',  InterclubResult::WIN],
            ['2026-01-23', "Braine l'Alleud I",     false, '0-16',  InterclubResult::WIN],
            ['2026-02-06', 'Logis Auderghem I',     false, '6-10',  InterclubResult::WIN],
            ['2026-02-13', 'Gremlins A',            true,  '16-0',  InterclubResult::WIN],
            ['2026-03-13', 'Tourinnes A',           true,  '12-4',  InterclubResult::WIN],
            ['2026-03-27', 'La Hulpe-Rix. C',       true,  '13-3',  InterclubResult::WIN],
            ['2026-04-05', 'Set-Jet Fleur Bleue D', false, '14-2',  InterclubResult::WIN],
        ]);

        $teamB = $this->team('B', $this->league('3B', LeagueCategory::MEN), '3ème place');
        $this->seedMatches($teamB, [
            ['2025-09-12', 'Arc En Ciel G',         true,  '8-8',   InterclubResult::DRAW],
            ['2025-09-19', 'REP Nivelles D',         false, '7-9',   InterclubResult::WIN],
            ['2025-09-26', 'Piranha G',              true,  '11-5',  InterclubResult::WIN],
            ['2025-10-10', 'Royal 1865 B',           true,  '6-10',  InterclubResult::LOSS],
            ['2025-10-18', 'TT Zenith Brussels B',   false, '6-10',  InterclubResult::WIN],
            ['2025-11-07', 'Logis Auderghem N',      false, '6-10',  InterclubResult::WIN],
            ['2025-11-14', 'La Hulpe Rix. D',        false, '11-5',  InterclubResult::LOSS],
            ['2025-11-21', 'Ry Ternel C',            true,  '5-11',  InterclubResult::LOSS],
            ['2025-11-28', 'Eveil D',                true,  '11-5',  InterclubResult::WIN],
            ['2026-01-09', 'Arc En Ciel G',          false, '6-10',  InterclubResult::WIN],
            ['2026-01-16', 'REP Nivelles D',         true,  '7-9',   InterclubResult::LOSS],
            ['2026-01-23', 'Piranha G',              false, '7-9',   InterclubResult::WIN],
            ['2026-02-06', 'Royal 1865 B',           false, '13-3',  InterclubResult::LOSS],
            ['2026-02-13', 'TT Zenith Brussels B',   true,  '12-4',  InterclubResult::WIN],
            ['2026-03-13', 'Logis Auderghem N',      true,  '7-9',   InterclubResult::LOSS],
            ['2026-03-27', 'La Hulpe-Rix. D',        true,  '10-6',  InterclubResult::WIN],
            ['2026-04-05', 'Ry Ternel C',            false, '16-0',  InterclubResult::WIN],
        ]);

        $teamC = $this->team('C', $this->league('4C', LeagueCategory::MEN), '5ème place');
        $this->seedMatches($teamC, [
            ['2025-09-12', 'CTT Limal Wavre F',      false, '13-3',  InterclubResult::WIN],
            ['2025-09-19', 'Safran A',                true,  '6-10',  InterclubResult::LOSS],
            ['2025-09-27', "Braine l'Alleud M",       false, '3-13',  InterclubResult::WIN],
            ['2025-10-10', 'Set-Jet Fleur Bleue K',   false, '1-15',  InterclubResult::WIN],
            ['2025-10-18', 'Mont St Guibert B',       true,  '6-10',  InterclubResult::LOSS],
            ['2025-11-07', 'Beauchamp A',             true,  '11-5',  InterclubResult::WIN],
            ['2025-11-14', 'Witterzee A',             true,  '2-14',  InterclubResult::LOSS],
            ['2025-11-21', 'Set-Jet Fleur Bleue J',   false, '3-13',  InterclubResult::WIN],
            ['2025-11-29', 'Logis Auderghem S',       false, '10-6',  InterclubResult::LOSS],
            ['2026-01-09', 'CTT Limal Wavre F',       true,  '6-10',  InterclubResult::LOSS],
            ['2026-01-16', 'Safran A',                false, '8-8',   InterclubResult::DRAW],
            ['2026-01-23', "Braine l'Alleud M",       true,  '7-9',   InterclubResult::LOSS],
            ['2026-02-06', 'Set-Jet Fleur Bleue K',   true,  '11-5',  InterclubResult::WIN],
            ['2026-02-13', 'Mont St Guibert B',       false, '10-6',  InterclubResult::WIN],
            ['2026-03-13', 'Beauchamp A',             false, '6-10',  InterclubResult::LOSS],
            ['2026-03-27', 'Witterzee A',             false, '0-16',  InterclubResult::LOSS],
            ['2026-04-05', 'Set-Jet Fleur Bleue J',   true,  '15-1',  InterclubResult::WIN],
        ]);

        $teamD = $this->team('D', $this->league('4D', LeagueCategory::MEN), '7ème place');
        $this->seedMatches($teamD, [
            ['2025-09-13', 'CTT Le Moulin C',         false, '14-2',  InterclubResult::WIN],
            ['2025-09-13', 'TT Zenith Brussels C',    true,  '1-15',  InterclubResult::LOSS],
            ['2025-09-27', 'Gremlins F',              false, '5-11',  InterclubResult::WIN],
            ['2025-10-10', 'Piranha I',               false, '6-10',  InterclubResult::WIN],
            ['2025-10-18', 'Eveil F',                 true,  '6-10',  InterclubResult::LOSS],
            ['2025-11-07', 'Arc En Ciel J',           true,  '5-11',  InterclubResult::LOSS],
            ['2025-11-14', 'Smash Evere C',           true,  '10-6',  InterclubResult::WIN],
            ['2025-11-21', 'Set-Jet Fleur Bleue F',   false, '6-10',  InterclubResult::LOSS],
            ['2025-11-28', 'Logis Auderghem Q',       false, '13-3',  InterclubResult::LOSS],
            ['2026-01-09', 'CTT Le Moulin C',         true,  '12-4',  InterclubResult::WIN],
            ['2026-01-16', 'TT Zenith Brussels C',    false, '13-3',  InterclubResult::LOSS],
            ['2026-01-23', 'Gremlins F',              true,  '6-10',  InterclubResult::LOSS],
            ['2026-02-06', 'Piranha I',               true,  '9-7',   InterclubResult::WIN],
            ['2026-02-13', 'Eveil F',                 false, '4-12',  InterclubResult::LOSS],
            ['2026-03-13', 'Arc En Ciel J',           false, '3-13',  InterclubResult::LOSS],
            ['2026-03-27', 'Smash Evere C',           false, '5-11',  InterclubResult::LOSS],
            ['2026-04-05', 'Set-Jet Fleur Bleue F',   true,  '9-7',   InterclubResult::WIN],
        ]);

        $teamE = $this->team('E', $this->league('5H', LeagueCategory::MEN), '7ème place');
        $this->seedMatches($teamE, [
            ['2025-09-12', 'CTT Alpa Schaerbeek P',  false, '6-10',  InterclubResult::LOSS],
            null,
            ['2025-09-26', 'Tourinnes E',            false, '0-16',  InterclubResult::LOSS],
            ['2025-10-10', "Braine l'Alleud O",      false, '11-5',  InterclubResult::LOSS],
            ['2025-10-18', 'Logis Auderghem 2',      true,  '4-12',  InterclubResult::LOSS],
            ['2025-11-07', 'Limal Wavre I',          true,  '4-12',  InterclubResult::LOSS],
            ['2025-11-14', 'Royal Clabecq G',        true,  '4-12',  InterclubResult::LOSS],
            ['2025-11-21', 'REP Nivelles H',         false, '3-13',  InterclubResult::WIN],
            null,
            ['2026-01-09', 'CTT Alpa Schaerbeek P', true,  '16-0',  InterclubResult::WIN],
            null,
            ['2026-01-23', 'Tourinnes E',            true,  '16-0',  InterclubResult::LOSS],
            ['2026-02-06', "Braine l'Alleud O",      true,  '4-12',  InterclubResult::LOSS],
            ['2026-02-13', 'Logis Auderghem 2',      false, '3-13',  InterclubResult::LOSS],
            ['2026-03-13', 'Limal Wavre I',          false, '4-12',  InterclubResult::LOSS],
            ['2026-03-27', 'Royal Clabecq G',        false, '1-15',  InterclubResult::LOSS],
            ['2026-04-05', 'REP Nivelles H',         true,  '7-9',   InterclubResult::LOSS],
        ]);
    }

    private function seedVeterans(): void
    {
        $teamVA = $this->team('A', $this->league('3B', LeagueCategory::VETERANS), '3ème place');
        $this->seedMatches($teamVA, [
            ['2025-10-03', 'Uccle Ping B',           true,  '10-0',  InterclubResult::WIN],
            ['2025-10-31', 'Limal Wavre C',          true,  '7-3',   InterclubResult::WIN],
            ['2025-12-05', 'Piranah C',              false, '5-5',   InterclubResult::DRAW],
            ['2026-01-30', 'Fonteny Genappe A',      true,  '3-7',   InterclubResult::LOSS],
            ['2026-02-20', 'Set-Jet Fleur Bleue C',  false, '4-6',   InterclubResult::WIN],
            ['2026-03-06', 'Gremlins B',             false, '6-4',   InterclubResult::LOSS],
            ['2026-03-20', 'REP Nivelles C',         false, '0-10',  InterclubResult::WIN],
        ]);

        $teamVB = $this->team('B', $this->league('3C', LeagueCategory::VETERANS), '7ème place');
        $this->seedMatches($teamVB, [
            ['2025-10-03', 'Logis Auderghem C',      false, '6-4',   InterclubResult::LOSS],
            ['2025-10-31', 'Arc En Ciel C',          true,  '3-7',   InterclubResult::LOSS],
            ['2025-12-05', 'TT Zenith Brussels A',   false, '5-5',   InterclubResult::DRAW],
            ['2026-01-30', 'Set-Jet Fleur Bleue D',  true,  '7-3',   InterclubResult::WIN],
            ['2026-02-20', 'Palette Bleue A',        false, '8-2',   InterclubResult::LOSS],
            ['2026-03-06', 'Le Moulin A',            false, '7-3',   InterclubResult::LOSS],
            ['2026-03-20', 'Eveil C',                false, '9-1',   InterclubResult::LOSS],
        ]);

        $teamVC = $this->team('C', $this->league('4F', LeagueCategory::VETERANS), '1ère place');
        $this->seedMatches($teamVC, [
            ['2025-10-03', 'Logis Auderghem D',      true,  '7-3',   InterclubResult::WIN],
            null,
            ['2025-12-05', 'Beauchamp B',            false, '1-9',   InterclubResult::WIN],
            ['2026-01-30', 'Mont St Guibert B',      true,  '7-3',   InterclubResult::WIN],
            ['2026-02-20', 'Gremlins D',             false, '2-8',   InterclubResult::WIN],
            ['2026-03-06', 'Uccle Ping C',           false, '3-7',   InterclubResult::WIN],
            ['2026-03-20', 'Set-Jet Fleur Bleue I',  false, '1-9',   InterclubResult::WIN],
        ]);
    }

    private function team(string $name, League $league, string $finalPosition): Team
    {
        $team = Team::firstOrCreate(
            ['name' => $name, 'season_id' => $this->season->id, 'league_id' => $league->id],
            ['club_id' => $this->club->id, 'final_position' => $finalPosition]
        );

        $team->update(['final_position' => $finalPosition]);

        return $team;
    }
}
