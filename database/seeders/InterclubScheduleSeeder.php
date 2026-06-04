<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubResult;
use App\Domains\Shared\Enums\LeagueCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InterclubScheduleSeeder extends Seeder
{
    private Club $ourClub;

    private Season $season;

    public function run(): void
    {
        $this->season  = Season::firstWhere('name', '2025-2026');
        $this->ourClub = Club::firstWhere('licence', config('app.club_licence'));

        $this->seedTeamA();
        $this->seedTeamB();
        $this->seedTeamC();
        $this->seedTeamD();
        $this->seedTeamE();
    }

    private function league(string $division, LeagueCategory $category): League
    {
        return League::firstWhere([
            'division'  => $division,
            'season_id' => $this->season->id,
            'category'  => $category->name,
        ]);
    }

    private function ourTeam(string $name, League $league): Team
    {
        $ourClubIds = Club::where('name', $this->ourClub->name)->pluck('id');

        return Team::whereIn('club_id', $ourClubIds)
            ->where([
                'name'      => $name,
                'season_id' => $this->season->id,
                'league_id' => $league->id,
            ])
            ->firstOrFail();
    }

    private function opponentClub(string $clubName): Club
    {
        return Club::firstOrCreate(
            ['name' => $clubName],
            [
                'licence'   => 'S-' . substr(md5($clubName), 0, 6),
                'is_active' => true,
            ]
        );
    }

    private function opponentTeam(string $opponentFullName, League $league): Team
    {
        // "Arc En Ciel F" → clubName="Arc En Ciel", letter="F"
        // "Logis Auderghem 2" → clubName="Logis Auderghem", letter="2"
        $parts      = explode(' ', $opponentFullName);
        $teamLetter = array_pop($parts);
        $clubName   = implode(' ', $parts);

        $club = $this->opponentClub($clubName);

        return Team::firstOrCreate([
            'name'      => $teamLetter,
            'league_id' => $league->id,
            'season_id' => $this->season->id,
            'club_id'   => $club->id,
        ]);
    }

    private function createInterclub(
        Team $ourTeam,
        string $opponentFullName,
        League $league,
        bool $isHome,
        string $date,
        ?string $score,
        ?InterclubResult $result
    ): void {
        $opponentTeam = $this->opponentTeam($opponentFullName, $league);

        Interclub::updateOrCreate(
            [
                'visited_team_id'  => $isHome ? $ourTeam->id : $opponentTeam->id,
                'visiting_team_id' => $isHome ? $opponentTeam->id : $ourTeam->id,
                'season_id'        => $this->season->id,
                'league_id'        => $league->id,
            ],
            [
                'start_date_time' => $date . ' 19:45:00',
                'week_number'     => Carbon::parse($date)->isoWeek(),
                'total_players'   => 4,
                'score'           => $score,
                'result'          => $result?->value,
                'address'         => '',
            ]
        );
    }

    private function seedTeamA(): void
    {
        $league  = $this->league('2C', LeagueCategory::MEN);
        $ourTeam = $this->ourTeam('A', $league);

        $matches = [
            ['2025-09-12', 'Arc En Ciel F',         true,  '15-1', InterclubResult::WIN],
            ['2025-09-21', 'TT Zenith Brussels A',  false, '7-9',  InterclubResult::WIN],
            ['2025-09-26', "Braine l'Alleud I",     true,  '14-2', InterclubResult::WIN],
            ['2025-10-10', 'Logis Auderghem I',     true,  '10-6', InterclubResult::WIN],
            ['2025-10-18', 'Gremlins A',            false, '3-13', InterclubResult::WIN],
            ['2025-11-07', 'Tourinnes A',           false, '9-7',  InterclubResult::LOSS],
            ['2025-11-14', 'La Hulpe-Rix. C',       false, '4-12', InterclubResult::WIN],
            ['2025-11-21', 'Set-Jet Fleur Bleue D', true,  '13-3', InterclubResult::WIN],
            ['2025-11-28', 'Eveil B',               true,  '16-0', InterclubResult::WIN],
            ['2026-01-09', 'Arc En Ciel F',         false, null,   InterclubResult::WITHDRAWAL_OPPONENT],
            ['2026-01-16', 'TT Zenith Brussels A',  true,  '11-5', InterclubResult::WIN],
            ['2026-01-23', "Braine l'Alleud I",     false, '0-16', InterclubResult::WIN],
            ['2026-02-06', 'Logis Auderghem I',     false, '6-10', InterclubResult::WIN],
            ['2026-02-13', 'Gremlins A',            true,  '16-0', InterclubResult::WIN],
            ['2026-03-13', 'Tourinnes A',           true,  '12-4', InterclubResult::WIN],
            ['2026-03-27', 'La Hulpe-Rix. C',       true,  '13-3', InterclubResult::WIN],
            ['2026-04-05', 'Set-Jet Fleur Bleue D', false, '14-2', InterclubResult::WIN],
            ['2026-04-17', 'Eveil B',               false, null,   null],
        ];

        foreach ($matches as [$date, $opponent, $isHome, $score, $result]) {
            $this->createInterclub($ourTeam, $opponent, $league, $isHome, $date, $score, $result);
        }
    }

    private function seedTeamB(): void
    {
        $league  = $this->league('3B', LeagueCategory::MEN);
        $ourTeam = $this->ourTeam('B', $league);

        $matches = [
            ['2025-09-12', 'Arc En Ciel G',        true,  '8-8',  InterclubResult::DRAW],
            ['2025-09-19', 'REP Nivelles D',        false, '7-9',  InterclubResult::WIN],
            ['2025-09-26', 'Piranha G',             true,  '11-5', InterclubResult::WIN],
            ['2025-10-10', 'Royal 1865 B',          true,  '6-10', InterclubResult::LOSS],
            ['2025-10-18', 'TT Zenith Brussels B',  false, '6-10', InterclubResult::WIN],
            ['2025-11-07', 'Logis Auderghem N',     false, '6-10', InterclubResult::WIN],
            ['2025-11-14', 'La Hulpe Rix. D',       false, '11-5', InterclubResult::LOSS],
            ['2025-11-21', 'Ry Ternel C',           true,  '5-11', InterclubResult::LOSS],
            ['2025-11-28', 'Eveil D',               true,  '11-5', InterclubResult::WIN],
            ['2026-01-09', 'Arc En Ciel G',         false, '6-10', InterclubResult::WIN],
            ['2026-01-16', 'REP Nivelles D',        true,  '7-9',  InterclubResult::LOSS],
            ['2026-01-23', 'Piranha G',             false, '7-9',  InterclubResult::WIN],
            ['2026-02-06', 'Royal 1865 B',          false, '13-3', InterclubResult::LOSS],
            ['2026-02-13', 'TT Zenith Brussels B',  true,  '12-4', InterclubResult::WIN],
            ['2026-03-13', 'Logis Auderghem N',     true,  '7-9',  InterclubResult::LOSS],
            ['2026-03-27', 'La Hulpe-Rix. D',       true,  '10-6', InterclubResult::WIN],
            ['2026-04-05', 'Ry Ternel C',           false, '16-0', InterclubResult::WIN],
            ['2026-04-17', 'Eveil D',               false, null,   null],
        ];

        foreach ($matches as [$date, $opponent, $isHome, $score, $result]) {
            $this->createInterclub($ourTeam, $opponent, $league, $isHome, $date, $score, $result);
        }
    }

    private function seedTeamC(): void
    {
        $league  = $this->league('4C', LeagueCategory::MEN);
        $ourTeam = $this->ourTeam('C', $league);

        $matches = [
            ['2025-09-12', 'CTT Limal Wavre F',     false, '13-3', InterclubResult::WIN],
            ['2025-09-19', 'Safran A',              true,  '6-10', InterclubResult::LOSS],
            ['2025-09-27', "Braine l'Alleud M",     false, '3-13', InterclubResult::WIN],
            ['2025-10-10', 'Set-Jet Fleur Bleue K', false, '1-15', InterclubResult::WIN],
            ['2025-10-18', 'Mont St Guibert B',     true,  '6-10', InterclubResult::LOSS],
            ['2025-11-07', 'Beauchamp A',           true,  '11-5', InterclubResult::WIN],
            ['2025-11-14', 'Witterzee A',           true,  '2-14', InterclubResult::LOSS],
            ['2025-11-21', 'Set-Jet Fleur Bleue J', false, '3-13', InterclubResult::WIN],
            ['2025-11-29', 'Logis Auderghem S',     false, '10-6', InterclubResult::LOSS],
            ['2026-01-09', 'CTT Limal Wavre F',     true,  '6-10', InterclubResult::LOSS],
            ['2026-01-16', 'Safran A',              false, '8-8',  InterclubResult::DRAW],
            ['2026-01-23', "Braine l'Alleud M",     true,  '7-9',  InterclubResult::LOSS],
            ['2026-02-06', 'Set-Jet Fleur Bleue K', true,  '11-5', InterclubResult::WIN],
            ['2026-02-13', 'Mont St Guibert B',     false, '10-6', InterclubResult::WIN],
            ['2026-03-13', 'Beauchamp A',           false, '6-10', InterclubResult::LOSS],
            ['2026-03-27', 'Witterzee A',           false, '0-16', InterclubResult::LOSS],
            ['2026-04-05', 'Set-Jet Fleur Bleue J', true,  '15-1', InterclubResult::WIN],
            ['2026-04-17', 'Logis Auderghem S',     true,  null,   null],
        ];

        foreach ($matches as [$date, $opponent, $isHome, $score, $result]) {
            $this->createInterclub($ourTeam, $opponent, $league, $isHome, $date, $score, $result);
        }
    }

    private function seedTeamD(): void
    {
        $league  = $this->league('4D', LeagueCategory::MEN);
        $ourTeam = $this->ourTeam('D', $league);

        $matches = [
            ['2025-09-13', 'CTT Le Moulin C',        false, '14-2', InterclubResult::WIN],
            ['2025-09-13', 'TT Zenith Brussels C',   true,  '1-15', InterclubResult::LOSS],
            ['2025-09-27', 'Gremlins F',             false, '5-11', InterclubResult::WIN],
            ['2025-10-10', 'Piranha I',              false, '6-10', InterclubResult::WIN],
            ['2025-10-18', 'Eveil F',                true,  '6-10', InterclubResult::LOSS],
            ['2025-11-07', 'Arc En Ciel J',          true,  '5-11', InterclubResult::LOSS],
            ['2025-11-14', 'Smash Evere C',          true,  '10-6', InterclubResult::WIN],
            ['2025-11-21', 'Set-Jet Fleur Bleue F',  false, '6-10', InterclubResult::LOSS],
            ['2025-11-28', 'Logis Auderghem Q',      false, '13-3', InterclubResult::LOSS],
            ['2026-01-09', 'CTT Le Moulin C',        true,  '12-4', InterclubResult::WIN],
            ['2026-01-16', 'TT Zenith Brussels C',   false, '13-3', InterclubResult::LOSS],
            ['2026-01-23', 'Gremlins F',             true,  '6-10', InterclubResult::LOSS],
            ['2026-02-06', 'Piranha I',              true,  '9-7',  InterclubResult::WIN],
            ['2026-02-13', 'Eveil F',                false, '4-12', InterclubResult::LOSS],
            ['2026-03-13', 'Arc En Ciel J',          false, '3-13', InterclubResult::LOSS],
            ['2026-03-27', 'Smash Evere C',          false, '5-11', InterclubResult::LOSS],
            ['2026-04-05', 'Set-Jet Fleur Bleue F',  true,  '9-7',  InterclubResult::WIN],
            ['2026-04-17', 'Logis Auderghem Q',      true,  null,   null],
        ];

        foreach ($matches as [$date, $opponent, $isHome, $score, $result]) {
            $this->createInterclub($ourTeam, $opponent, $league, $isHome, $date, $score, $result);
        }
    }

    private function seedTeamE(): void
    {
        $league  = $this->league('5H', LeagueCategory::MEN);
        $ourTeam = $this->ourTeam('E', $league);

        // Byes are skipped — no Interclub record created (including April 17 which is also a bye)
        $matches = [
            ['2025-09-12', 'CTT Alpa Schaerbeek P', false, '6-10',  InterclubResult::LOSS],
            ['2025-09-26', 'Tourinnes E',           false, '0-16',  InterclubResult::LOSS],
            ['2025-10-10', "Braine l'Alleud O",     false, '11-5',  InterclubResult::LOSS],
            ['2025-10-18', 'Logis Auderghem 2',     true,  '4-12',  InterclubResult::LOSS],
            ['2025-11-07', 'Limal Wavre I',         true,  '4-12',  InterclubResult::LOSS],
            ['2025-11-14', 'Royal Clabecq G',       true,  '4-12',  InterclubResult::LOSS],
            ['2025-11-21', 'REP Nivelles H',        false, '3-13',  InterclubResult::WIN],
            ['2026-01-09', 'CTT Alpa Schaerbeek P', true,  '16-0',  InterclubResult::WIN],
            ['2026-01-23', 'Tourinnes E',           true,  '16-0',  InterclubResult::LOSS],
            ['2026-02-06', "Braine l'Alleud O",     true,  '4-12',  InterclubResult::LOSS],
            ['2026-02-13', 'Logis Auderghem 2',     false, '3-13',  InterclubResult::LOSS],
            ['2026-03-13', 'Limal Wavre I',         false, '4-12',  InterclubResult::LOSS],
            ['2026-03-27', 'Royal Clabecq G',       false, '1-15',  InterclubResult::LOSS],
            ['2026-04-05', 'REP Nivelles H',        true,  '7-9',   InterclubResult::LOSS],
        ];

        foreach ($matches as [$date, $opponent, $isHome, $score, $result]) {
            $this->createInterclub($ourTeam, $opponent, $league, $isHome, $date, $score, $result);
        }
    }
}
