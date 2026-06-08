<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Production-safe seeder: no factories, no Faker.
 * Single player = user id 1 (captain of all teams).
 */
class InterclubProdSeeder extends Seeder
{
    private const CLUB_LICENCE_MAP = [
        'Arc En Ciel' => 'BBW134',
        'TT Zénith Brussels' => 'BBW205',
        "Braine-l'Alleud" => 'BBW179',
        'Logis Auderghem' => 'BBW165',
        'Gremlins' => 'BBW326',
        'Tourinnes' => 'BBW350',
        'La Hulpe-Rixensart' => 'BBW194',
        'Set-Jet Fleur Bleue' => 'BBW034',
        'Watermael-Boitsfort' => 'BBW299',
        'REP Nivellois' => 'BBW118',
        'Piranha' => 'BBW319',
        'Royal 1865 Waterloo' => 'BBW147',
        'CTT Limal' => 'BBW123',
        'Mont-Saint-Guibert' => 'BBW223',
        'Beauchamp' => 'BBW345',
        'Witterzee' => 'BBW291',
        'Safran' => 'BBW190',
        'CTT Alpa Schaerbeek' => 'BBW015',
        'Royal Clabecq' => 'BBW155',
        'CTT Le Moulin' => 'BBW315',
        'Le Moulin' => 'BBW315',
        'Fonteny-Genappe' => 'BBW338',
        'Uccle Ping' => 'BBW347',
        'Palette Bleue' => 'BBW348',
        'Smash Evere' => 'BBW323',
        'Eveil' => 'BBW321',
        'TT Perwez' => 'BBW289',
    ];

    private ?Club $club;

    private User $me;

    private Season $season;

    public function run(): void
    {
        $this->me = User::findOrFail(1);

        Season::query()->update(['is_active' => false]);
        Cache::forget('season.current');

        $this->season = Season::updateOrCreate(
            ['name' => '2026-2027'],
            [
                'start_at' => '2026-09-01 00:00:00',
                'end_at' => '2027-06-30 00:00:00',
                'is_active' => true,
            ]
        );

        $this->club = Club::own();

        if (! $this->club) {
            $this->command->warn('No own club found. Skipping InterclubProdSeeder.');

            return;
        }

        $this->cleanSeason();

        $this->seedMen();
        $this->seedVeterans();
        $this->seedWomen();
    }

    private function cleanSeason(): void
    {
        $seasonId = $this->season->id;

        $interclubIds = Interclub::where('season_id', $seasonId)->pluck('id');

        if ($interclubIds->isNotEmpty()) {
            DB::table('interclub_user')->whereIn('interclub_id', $interclubIds)->delete();
            Interclub::whereIn('id', $interclubIds)->delete();
        }

        InterclubResult::where('season_id', $seasonId)->delete();

        $ourTeamIds = Team::whereHas('club', fn ($q) => $q->where('is_own_club', true))
            ->where('season_id', $seasonId)
            ->pluck('id');

        if ($ourTeamIds->isNotEmpty()) {
            DB::table('team_user')->whereIn('team_id', $ourTeamIds)->delete();
            Team::whereIn('id', $ourTeamIds)->delete();
        }
    }

    private function createInterclub(Team $ourTeam, Team $opponentTeam, string $dateTime, bool $isHome): Interclub
    {
        $visitedId = $isHome ? $ourTeam->id : $opponentTeam->id;
        $visitingId = $isHome ? $opponentTeam->id : $ourTeam->id;

        $homeClub = $isHome ? $this->club : $opponentTeam->club;
        $homeAddress = $homeClub?->street ?? ($isHome ? 'Rue de l\'invasion 80, 1340 Ottignies' : 'Salle adverse');

        return Interclub::create([
            'visited_team_id' => $visitedId,
            'visiting_team_id' => $visitingId,
            'start_date_time' => Carbon::parse($dateTime),
            'address' => $homeAddress,
            'total_players' => $ourTeam->league?->category === 'MEN' ? 4 : 3,
            'week_number' => Carbon::parse($dateTime)->isoWeek(),
            'season_id' => $this->season->id,
            'league_id' => $ourTeam->league_id,
        ]);
    }

    private function league(string $division, LeagueCategory $category, LeagueLevel $level = LeagueLevel::PROVINCIAL_BW): League
    {
        return League::firstOrCreate(
            ['division' => $division, 'season_id' => $this->season->id, 'category' => $category->name],
            ['level' => $level->name]
        );
    }

    private function opponentTeam(string $clubName, string $teamLetter, League $league, ?string $address = null): Team
    {
        $licence = self::CLUB_LICENCE_MAP[$clubName] ?? null;

        if ($licence) {
            $opponentClub = Club::firstOrCreate(
                ['licence' => $licence],
                ['name' => $clubName, 'city_name' => $clubName, 'street' => $address]
            );
        } else {
            $suffix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $clubName), 0, 6));
            $opponentClub = Club::firstOrCreate(
                ['name' => $clubName],
                ['licence' => 'OPP-' . $suffix, 'city_name' => $clubName, 'street' => $address]
            );
        }

        return Team::firstOrCreate(
            ['name' => $teamLetter, 'season_id' => $this->season->id, 'league_id' => $league->id, 'club_id' => $opponentClub->id],
        );
    }

    private function seedMen(): void
    {
        $leagueA = $this->league('P2A', LeagueCategory::MEN);
        $leagueB = $this->league('P3B', LeagueCategory::MEN);
        $leagueC = $this->league('P3C', LeagueCategory::MEN);
        $leagueD = $this->league('P4A', LeagueCategory::MEN);
        $leagueE = $this->league('P5B', LeagueCategory::MEN);

        $teamA = $this->team('A', $leagueA);
        $teamB = $this->team('B', $leagueB);
        $teamC = $this->team('C', $leagueC);
        $teamD = $this->team('D', $leagueD);
        $teamE = $this->team('E', $leagueE);

        // ── Équipe A (P2A)
        $a1 = $this->opponentTeam('Arc En Ciel', 'F', $leagueA, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $a2 = $this->opponentTeam('TT Zénith Brussels', 'A', $leagueA, 'Rue de Birmingham 115, 1070 Anderlecht');
        $a3 = $this->opponentTeam('Braine-l\'Alleud', 'I', $leagueA, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');
        $a4 = $this->opponentTeam('Logis Auderghem', 'I', $leagueA, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $a5 = $this->opponentTeam('Gremlins', 'A', $leagueA, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $a6 = $this->opponentTeam('Tourinnes', 'A', $leagueA, 'Rue des Templiers 5, 1320 Beauvechain');
        $a7 = $this->opponentTeam('La Hulpe-Rixensart', 'C', $leagueA, 'Rue du Moulin 15, 1310 La Hulpe');
        $a8 = $this->opponentTeam('Set-Jet Fleur Bleue', 'D', $leagueA, 'Avenue du Comté de Jette 3, 1090 Jette');
        $a9 = $this->opponentTeam('Watermael-Boitsfort', 'A', $leagueA, 'Chaussée de Maelbeek 24, 1170 Watermael-Boitsfort');

        $this->createInterclub($teamA, $a1, '2026-09-11 20:00', true);
        $this->createInterclub($teamA, $a2, '2026-09-18 20:00', false);
        $this->createInterclub($teamA, $a3, '2026-09-25 20:00', true);
        $this->createInterclub($teamA, $a4, '2026-10-02 20:00', false);
        $this->createInterclub($teamA, $a5, '2026-10-09 20:00', true);
        $this->createInterclub($teamA, $a6, '2026-10-16 20:00', false);
        $this->createInterclub($teamA, $a7, '2026-10-23 20:00', true);
        $this->createInterclub($teamA, $a8, '2026-10-30 20:00', false);
        $this->createInterclub($teamA, $a9, '2026-11-06 20:00', true);
        $this->createInterclub($teamA, $a1, '2027-01-08 20:00', false);
        $this->createInterclub($teamA, $a2, '2027-01-15 20:00', true);
        $this->createInterclub($teamA, $a3, '2027-01-22 20:00', false);
        $this->createInterclub($teamA, $a4, '2027-01-29 20:00', true);
        $this->createInterclub($teamA, $a5, '2027-02-05 20:00', false);
        $this->createInterclub($teamA, $a6, '2027-02-12 20:00', true);
        $this->createInterclub($teamA, $a7, '2027-02-19 20:00', false);
        $this->createInterclub($teamA, $a8, '2027-02-26 20:00', true);
        $this->createInterclub($teamA, $a9, '2027-03-05 20:00', false);

        // ── Équipe B (P3B)
        $b1 = $this->opponentTeam('Arc En Ciel', 'G', $leagueB, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $b2 = $this->opponentTeam('REP Nivellois', 'D', $leagueB, 'Rue des Heures Claires 46, 1400 Nivelles');
        $b3 = $this->opponentTeam('Piranha', 'G', $leagueB, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $b4 = $this->opponentTeam('Royal 1865 Waterloo', 'B', $leagueB, 'Boulevard de la Résistance 1, 1410 Waterloo');
        $b5 = $this->opponentTeam('TT Zénith Brussels', 'B', $leagueB, 'Rue de Birmingham 115, 1070 Anderlecht');
        $b6 = $this->opponentTeam('Logis Auderghem', 'N', $leagueB, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $b7 = $this->opponentTeam('La Hulpe-Rixensart', 'D', $leagueB, 'Rue du Moulin 15, 1310 La Hulpe');
        $b8 = $this->opponentTeam('CTT Limal', 'H', $leagueB, 'Rue Charles Jaumotte 156, 1300 Limal');
        $b9 = $this->opponentTeam('Watermael-Boitsfort', 'B', $leagueB, 'Chaussée de Maelbeek 24, 1170 Watermael-Boitsfort');

        $this->createInterclub($teamB, $b1, '2026-09-11 20:00', false);
        $this->createInterclub($teamB, $b2, '2026-09-18 20:00', true);
        $this->createInterclub($teamB, $b3, '2026-09-25 20:00', false);
        $this->createInterclub($teamB, $b4, '2026-10-02 20:00', true);
        $this->createInterclub($teamB, $b5, '2026-10-09 20:00', false);
        $this->createInterclub($teamB, $b6, '2026-10-16 20:00', true);
        $this->createInterclub($teamB, $b7, '2026-10-23 20:00', false);
        $this->createInterclub($teamB, $b8, '2026-10-30 20:00', true);
        $this->createInterclub($teamB, $b9, '2026-11-06 20:00', false);
        $this->createInterclub($teamB, $b1, '2027-01-08 20:00', true);
        $this->createInterclub($teamB, $b2, '2027-01-15 20:00', false);
        $this->createInterclub($teamB, $b3, '2027-01-22 20:00', true);
        $this->createInterclub($teamB, $b4, '2027-01-29 20:00', false);
        $this->createInterclub($teamB, $b5, '2027-02-05 20:00', true);
        $this->createInterclub($teamB, $b6, '2027-02-12 20:00', false);
        $this->createInterclub($teamB, $b7, '2027-02-19 20:00', true);
        $this->createInterclub($teamB, $b8, '2027-02-26 20:00', false);
        $this->createInterclub($teamB, $b9, '2027-03-05 20:00', true);

        // ── Équipe C (P3C)
        $c1 = $this->opponentTeam('CTT Limal', 'F', $leagueC, 'Rue Charles Jaumotte 156, 1300 Limal');
        $c2 = $this->opponentTeam('Safran', 'A', $leagueC, 'Rue Emile Francqui 1, 1140 Evere');
        $c3 = $this->opponentTeam('Braine-l\'Alleud', 'M', $leagueC, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');
        $c4 = $this->opponentTeam('Set-Jet Fleur Bleue', 'K', $leagueC, 'Avenue du Comté de Jette 3, 1090 Jette');
        $c5 = $this->opponentTeam('Mont-Saint-Guibert', 'B', $leagueC, 'Chaussée de Louvain 14, 1435 Mont-Saint-Guibert');
        $c6 = $this->opponentTeam('Beauchamp', 'A', $leagueC, 'Rue de la Victoire 5, 1400 Nivelles');
        $c7 = $this->opponentTeam('Witterzee', 'A', $leagueC, 'Rue Joseph Lacoste 3, 1332 Rixensart');
        $c8 = $this->opponentTeam('Logis Auderghem', 'O', $leagueC, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $c9 = $this->opponentTeam('TT Zénith Brussels', 'C', $leagueC, 'Rue de Birmingham 115, 1070 Anderlecht');

        $this->createInterclub($teamC, $c1, '2026-09-12 14:00', false);
        $this->createInterclub($teamC, $c2, '2026-09-19 14:00', true);
        $this->createInterclub($teamC, $c3, '2026-09-26 14:00', false);
        $this->createInterclub($teamC, $c4, '2026-10-03 14:00', true);
        $this->createInterclub($teamC, $c5, '2026-10-10 14:00', false);
        $this->createInterclub($teamC, $c6, '2026-10-17 14:00', true);
        $this->createInterclub($teamC, $c7, '2026-10-24 14:00', false);
        $this->createInterclub($teamC, $c8, '2026-10-31 14:00', true);
        $this->createInterclub($teamC, $c9, '2026-11-07 14:00', false);
        $this->createInterclub($teamC, $c1, '2027-01-09 14:00', true);
        $this->createInterclub($teamC, $c2, '2027-01-16 14:00', false);
        $this->createInterclub($teamC, $c3, '2027-01-23 14:00', true);
        $this->createInterclub($teamC, $c4, '2027-01-30 14:00', false);
        $this->createInterclub($teamC, $c5, '2027-02-06 14:00', true);
        $this->createInterclub($teamC, $c6, '2027-02-13 14:00', false);
        $this->createInterclub($teamC, $c7, '2027-02-20 14:00', true);
        $this->createInterclub($teamC, $c8, '2027-02-27 14:00', false);
        $this->createInterclub($teamC, $c9, '2027-03-06 14:00', true);

        // ── Équipe D (P4A)
        $d1 = $this->opponentTeam('CTT Le Moulin', 'C', $leagueD, 'Route du Moulin 4, 1380 Lasne');
        $d2 = $this->opponentTeam('TT Zénith Brussels', 'C', $leagueD, 'Rue de Birmingham 115, 1070 Anderlecht');
        $d3 = $this->opponentTeam('Gremlins', 'F', $leagueD, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $d4 = $this->opponentTeam('Piranha', 'I', $leagueD, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $d5 = $this->opponentTeam('Eveil', 'F', $leagueD, 'Rue du Frontispice 2, 1140 Evere');
        $d6 = $this->opponentTeam('Arc En Ciel', 'J', $leagueD, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $d7 = $this->opponentTeam('Smash Evere', 'C', $leagueD, 'Avenue de la Reine 31, 1140 Evere');
        $d8 = $this->opponentTeam('Braine-l\'Alleud', 'Q', $leagueD, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');
        $d9 = $this->opponentTeam('Arc En Ciel', 'K', $leagueD, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');

        $this->createInterclub($teamD, $d1, '2026-09-12 20:00', false);
        $this->createInterclub($teamD, $d2, '2026-09-19 20:00', true);
        $this->createInterclub($teamD, $d3, '2026-09-26 20:00', false);
        $this->createInterclub($teamD, $d4, '2026-10-03 20:00', true);
        $this->createInterclub($teamD, $d5, '2026-10-10 20:00', false);
        $this->createInterclub($teamD, $d6, '2026-10-17 20:00', true);
        $this->createInterclub($teamD, $d7, '2026-10-24 20:00', false);
        $this->createInterclub($teamD, $d8, '2026-10-31 20:00', true);
        $this->createInterclub($teamD, $d9, '2026-11-07 20:00', false);
        $this->createInterclub($teamD, $d1, '2027-01-09 20:00', true);
        $this->createInterclub($teamD, $d2, '2027-01-16 20:00', false);
        $this->createInterclub($teamD, $d3, '2027-01-23 20:00', true);
        $this->createInterclub($teamD, $d4, '2027-01-30 20:00', false);
        $this->createInterclub($teamD, $d5, '2027-02-06 20:00', true);
        $this->createInterclub($teamD, $d6, '2027-02-13 20:00', false);
        $this->createInterclub($teamD, $d7, '2027-02-20 20:00', true);
        $this->createInterclub($teamD, $d8, '2027-02-27 20:00', false);
        $this->createInterclub($teamD, $d9, '2027-03-06 20:00', true);

        // ── Équipe E (P5B)
        $e1 = $this->opponentTeam('CTT Alpa Schaerbeek', 'P', $leagueE, 'Rue Adolphe Demeure 16, 1030 Schaerbeek');
        $e2 = $this->opponentTeam('Tourinnes', 'E', $leagueE, 'Rue des Templiers 5, 1320 Beauvechain');
        $e3 = $this->opponentTeam('Braine-l\'Alleud', 'O', $leagueE, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');
        $e4 = $this->opponentTeam('Logis Auderghem', '2', $leagueE, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $e5 = $this->opponentTeam('CTT Limal', 'I', $leagueE, 'Rue Charles Jaumotte 156, 1300 Limal');
        $e6 = $this->opponentTeam('Royal Clabecq', 'G', $leagueE, 'Rue du Sart 2, 1480 Tubize');
        $e7 = $this->opponentTeam('Beauchamp', 'B', $leagueE, 'Rue de la Victoire 5, 1400 Nivelles');
        $e8 = $this->opponentTeam('Mont-Saint-Guibert', 'C', $leagueE, 'Chaussée de Louvain 14, 1435 Mont-Saint-Guibert');
        $e9 = $this->opponentTeam('La Hulpe-Rixensart', 'F', $leagueE, 'Rue du Moulin 15, 1310 La Hulpe');

        $this->createInterclub($teamE, $e1, '2026-09-10 20:00', false);
        $this->createInterclub($teamE, $e2, '2026-09-24 20:00', false);
        $this->createInterclub($teamE, $e3, '2026-10-08 20:00', false);
        $this->createInterclub($teamE, $e4, '2026-10-22 20:00', true);
        $this->createInterclub($teamE, $e5, '2026-11-05 20:00', true);
        $this->createInterclub($teamE, $e6, '2026-11-19 20:00', true);
        $this->createInterclub($teamE, $e7, '2026-12-03 20:00', false);
        $this->createInterclub($teamE, $e8, '2026-12-10 20:00', true);
        $this->createInterclub($teamE, $e9, '2026-12-17 20:00', false);
        $this->createInterclub($teamE, $e1, '2027-01-07 20:00', true);
        $this->createInterclub($teamE, $e2, '2027-01-21 20:00', true);
        $this->createInterclub($teamE, $e3, '2027-02-04 20:00', true);
        $this->createInterclub($teamE, $e4, '2027-02-18 20:00', false);
        $this->createInterclub($teamE, $e5, '2027-03-04 20:00', false);
        $this->createInterclub($teamE, $e6, '2027-03-18 20:00', false);
        $this->createInterclub($teamE, $e7, '2027-04-01 20:00', true);
        $this->createInterclub($teamE, $e8, '2027-04-15 20:00', false);
        $this->createInterclub($teamE, $e9, '2027-04-29 20:00', true);
    }

    private function seedVeterans(): void
    {
        $leagueA = $this->league('3B', LeagueCategory::VETERANS);
        $leagueB = $this->league('3C', LeagueCategory::VETERANS);
        $leagueC = $this->league('4F', LeagueCategory::VETERANS);

        $teamA = $this->team('A', $leagueA);
        $teamB = $this->team('B', $leagueB);
        $teamC = $this->team('C', $leagueC);

        // ── Vét. A (3B)
        $va1 = $this->opponentTeam('Uccle Ping', 'B', $leagueA, 'Chaussée de Waterloo 1475, 1180 Uccle');
        $va2 = $this->opponentTeam('CTT Limal', 'C', $leagueA, 'Rue Charles Jaumotte 156, 1300 Limal');
        $va3 = $this->opponentTeam('Piranha', 'C', $leagueA, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $va4 = $this->opponentTeam('Fonteny-Genappe', 'A', $leagueA, 'Rue du Centre 3, 1470 Genappe');
        $va5 = $this->opponentTeam('Set-Jet Fleur Bleue', 'C', $leagueA, 'Avenue du Comté de Jette 3, 1090 Jette');
        $va6 = $this->opponentTeam('Gremlins', 'B', $leagueA, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $va7 = $this->opponentTeam('TT Zénith Brussels', 'B', $leagueA, 'Rue de Birmingham 115, 1070 Anderlecht');
        $va8 = $this->opponentTeam('Piranha', 'D', $leagueA, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $va9 = $this->opponentTeam('Watermael-Boitsfort', 'A', $leagueA, 'Chaussée de Maelbeek 24, 1170 Watermael-Boitsfort');

        $this->createInterclub($teamA, $va1, '2026-10-04 10:00', true);
        $this->createInterclub($teamA, $va2, '2026-10-11 10:00', false);
        $this->createInterclub($teamA, $va3, '2026-10-18 10:00', true);
        $this->createInterclub($teamA, $va4, '2026-11-01 10:00', false);
        $this->createInterclub($teamA, $va5, '2026-11-08 10:00', true);
        $this->createInterclub($teamA, $va6, '2026-11-15 10:00', false);
        $this->createInterclub($teamA, $va7, '2026-11-22 10:00', true);
        $this->createInterclub($teamA, $va8, '2026-12-06 10:00', false);
        $this->createInterclub($teamA, $va9, '2026-12-13 10:00', true);
        $this->createInterclub($teamA, $va1, '2027-01-17 10:00', false);
        $this->createInterclub($teamA, $va2, '2027-01-24 10:00', true);
        $this->createInterclub($teamA, $va3, '2027-01-31 10:00', false);
        $this->createInterclub($teamA, $va4, '2027-02-07 10:00', true);
        $this->createInterclub($teamA, $va5, '2027-02-14 10:00', false);
        $this->createInterclub($teamA, $va6, '2027-02-21 10:00', true);
        $this->createInterclub($teamA, $va7, '2027-02-28 10:00', false);
        $this->createInterclub($teamA, $va8, '2027-03-07 10:00', true);
        $this->createInterclub($teamA, $va9, '2027-03-14 10:00', false);

        // ── Vét. B (3C)
        $vb1 = $this->opponentTeam('Logis Auderghem', 'C', $leagueB, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $vb2 = $this->opponentTeam('Arc En Ciel', 'C', $leagueB, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $vb3 = $this->opponentTeam('TT Zénith Brussels', 'A', $leagueB, 'Rue de Birmingham 115, 1070 Anderlecht');
        $vb4 = $this->opponentTeam('Set-Jet Fleur Bleue', 'D', $leagueB, 'Avenue du Comté de Jette 3, 1090 Jette');
        $vb5 = $this->opponentTeam('Palette Bleue', 'A', $leagueB, 'Avenue Stalingrad 30, 1000 Bruxelles');
        $vb6 = $this->opponentTeam('Le Moulin', 'A', $leagueB, 'Route du Moulin 4, 1380 Lasne');
        $vb7 = $this->opponentTeam('CTT Limal', 'D', $leagueB, 'Rue Charles Jaumotte 156, 1300 Limal');
        $vb8 = $this->opponentTeam('Gremlins', 'C', $leagueB, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $vb9 = $this->opponentTeam('Braine-l\'Alleud', 'B', $leagueB, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');

        $this->createInterclub($teamB, $vb1, '2026-10-04 10:00', false);
        $this->createInterclub($teamB, $vb2, '2026-10-11 10:00', true);
        $this->createInterclub($teamB, $vb3, '2026-10-18 10:00', false);
        $this->createInterclub($teamB, $vb4, '2026-11-01 10:00', true);
        $this->createInterclub($teamB, $vb5, '2026-11-08 10:00', false);
        $this->createInterclub($teamB, $vb6, '2026-11-15 10:00', true);
        $this->createInterclub($teamB, $vb7, '2026-11-22 10:00', false);
        $this->createInterclub($teamB, $vb8, '2026-12-06 10:00', true);
        $this->createInterclub($teamB, $vb9, '2026-12-13 10:00', false);
        $this->createInterclub($teamB, $vb1, '2027-01-17 10:00', true);
        $this->createInterclub($teamB, $vb2, '2027-01-24 10:00', false);
        $this->createInterclub($teamB, $vb3, '2027-01-31 10:00', true);
        $this->createInterclub($teamB, $vb4, '2027-02-07 10:00', false);
        $this->createInterclub($teamB, $vb5, '2027-02-14 10:00', true);
        $this->createInterclub($teamB, $vb6, '2027-02-21 10:00', false);
        $this->createInterclub($teamB, $vb7, '2027-02-28 10:00', true);
        $this->createInterclub($teamB, $vb8, '2027-03-07 10:00', false);
        $this->createInterclub($teamB, $vb9, '2027-03-14 10:00', true);

        // ── Vét. C (4F)
        $vc1 = $this->opponentTeam('Logis Auderghem', 'D', $leagueC, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $vc2 = $this->opponentTeam('Beauchamp', 'B', $leagueC, 'Rue de la Victoire 5, 1400 Nivelles');
        $vc3 = $this->opponentTeam('Mont-Saint-Guibert', 'B', $leagueC, 'Chaussée de Louvain 14, 1435 Mont-Saint-Guibert');
        $vc4 = $this->opponentTeam('Gremlins', 'D', $leagueC, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $vc5 = $this->opponentTeam('Uccle Ping', 'C', $leagueC, 'Chaussée de Waterloo 1475, 1180 Uccle');
        $vc6 = $this->opponentTeam('Arc En Ciel', 'E', $leagueC, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $vc7 = $this->opponentTeam('REP Nivellois', 'A', $leagueC, 'Rue des Heures Claires 46, 1400 Nivelles');
        $vc8 = $this->opponentTeam('Piranha', 'E', $leagueC, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $vc9 = $this->opponentTeam('La Hulpe-Rixensart', 'B', $leagueC, 'Rue du Moulin 15, 1310 La Hulpe');

        $this->createInterclub($teamC, $vc1, '2026-10-04 14:00', true);
        $this->createInterclub($teamC, $vc2, '2026-10-11 14:00', false);
        $this->createInterclub($teamC, $vc3, '2026-10-18 14:00', true);
        $this->createInterclub($teamC, $vc4, '2026-11-01 14:00', false);
        $this->createInterclub($teamC, $vc5, '2026-11-08 14:00', true);
        $this->createInterclub($teamC, $vc6, '2026-11-15 14:00', false);
        $this->createInterclub($teamC, $vc7, '2026-11-22 14:00', true);
        $this->createInterclub($teamC, $vc8, '2026-12-06 14:00', false);
        $this->createInterclub($teamC, $vc9, '2026-12-13 14:00', true);
        $this->createInterclub($teamC, $vc1, '2027-01-17 14:00', false);
        $this->createInterclub($teamC, $vc2, '2027-01-24 14:00', true);
        $this->createInterclub($teamC, $vc3, '2027-01-31 14:00', false);
        $this->createInterclub($teamC, $vc4, '2027-02-07 14:00', true);
        $this->createInterclub($teamC, $vc5, '2027-02-14 14:00', false);
        $this->createInterclub($teamC, $vc6, '2027-02-21 14:00', true);
        $this->createInterclub($teamC, $vc7, '2027-02-28 14:00', false);
        $this->createInterclub($teamC, $vc8, '2027-03-07 14:00', true);
        $this->createInterclub($teamC, $vc9, '2027-03-14 14:00', false);
    }

    private function seedWomen(): void
    {
        $league = $this->league('2A', LeagueCategory::WOMEN);
        $team = $this->team('A', $league);

        $w1 = $this->opponentTeam('La Hulpe-Rixensart', 'A', $league, 'Rue du Moulin 15, 1310 La Hulpe');
        $w2 = $this->opponentTeam('REP Nivellois', 'A', $league, 'Rue des Heures Claires 46, 1400 Nivelles');
        $w3 = $this->opponentTeam('Royal 1865 Waterloo', 'A', $league, 'Boulevard de la Résistance 1, 1410 Waterloo');
        $w4 = $this->opponentTeam('TT Perwez', 'A', $league, 'Rue du Presbytère 5, 5310 Perwez');
        $w5 = $this->opponentTeam('CTT Limal', 'A', $league, 'Rue Charles Jaumotte 156, 1300 Limal');
        $w6 = $this->opponentTeam('Tourinnes', 'A', $league, 'Rue des Templiers 5, 1320 Beauvechain');
        $w7 = $this->opponentTeam('CTT Limal', 'B', $league, 'Rue Charles Jaumotte 156, 1300 Limal');
        $w8 = $this->opponentTeam('Arc En Ciel', 'A', $league, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $w9 = $this->opponentTeam('Piranha', 'A', $league, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');

        $this->createInterclub($team, $w1, '2026-09-12 14:00', false);
        $this->createInterclub($team, $w2, '2026-09-26 14:00', true);
        $this->createInterclub($team, $w3, '2026-10-10 14:00', false);
        $this->createInterclub($team, $w4, '2026-10-24 14:00', true);
        $this->createInterclub($team, $w5, '2026-11-07 14:00', false);
        $this->createInterclub($team, $w6, '2026-11-21 14:00', true);
        $this->createInterclub($team, $w7, '2026-12-05 14:00', false);
        $this->createInterclub($team, $w8, '2026-12-12 14:00', true);
        $this->createInterclub($team, $w9, '2026-12-19 14:00', false);
        $this->createInterclub($team, $w1, '2027-01-09 14:00', true);
        $this->createInterclub($team, $w2, '2027-01-23 14:00', false);
        $this->createInterclub($team, $w3, '2027-02-06 14:00', true);
        $this->createInterclub($team, $w4, '2027-02-20 14:00', false);
        $this->createInterclub($team, $w5, '2027-03-06 14:00', true);
        $this->createInterclub($team, $w6, '2027-03-20 14:00', false);
        $this->createInterclub($team, $w7, '2027-04-03 14:00', true);
        $this->createInterclub($team, $w8, '2027-04-17 14:00', false);
        $this->createInterclub($team, $w9, '2027-05-01 14:00', true);
    }

    private function team(string $name, League $league): Team
    {
        $team = Team::firstOrCreate(
            ['name' => $name, 'season_id' => $this->season->id, 'league_id' => $league->id, 'club_id' => $this->club->id],
            ['captain_id' => $this->me->id]
        );

        $team->update(['captain_id' => $this->me->id]);
        $team->users()->syncWithoutDetaching([$this->me->id]);

        return $team;
    }
}
