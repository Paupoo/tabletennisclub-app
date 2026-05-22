<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\InterclubAvailability;
use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\MatchResult;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InterclubSeeder extends Seeder
{
    private Club $club;

    private Season $season;

    public function run(): void
    {
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

        $this->club = Club::firstWhere('licence', config('app.club_licence'));

        if (! $this->club) {
            $this->command->warn('No club found with licence ' . config('app.club_licence') . '. Skipping InterclubSeeder.');

            return;
        }

        $this->cleanSeason();

        $this->seedMen();
        $this->seedVeterans();
        $this->seedWomen();
    }

    private function assignPlayers(Team $team, int $count): void
    {
        $existing = $team->users()->count();

        if ($existing >= $count) {
            return;
        }

        $needed = $count - $existing;
        $players = User::factory($needed)->isCompetitor()->create(['club_id' => $this->club->id]);

        foreach ($players as $player) {
            $team->users()->syncWithoutDetaching([$player->id]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Clean all data for the seeded season before recreating it
    // ─────────────────────────────────────────────────────────────────────────
    private function cleanSeason(): void
    {
        $seasonId = $this->season->id;

        $interclubIds = Interclub::where('season_id', $seasonId)->pluck('id');

        if ($interclubIds->isNotEmpty()) {
            DB::table('interclub_user')->whereIn('interclub_id', $interclubIds)->delete();
            Interclub::whereIn('id', $interclubIds)->delete();
        }

        MatchResult::where('season_id', $seasonId)->delete();

        $ourTeamIds = Team::whereHas('club', fn ($q) => $q->where('licence', config('app.club_licence')))
            ->where('season_id', $seasonId)
            ->pluck('id');

        if ($ourTeamIds->isNotEmpty()) {
            DB::table('team_user')->whereIn('team_id', $ourTeamIds)->delete();
            Team::whereIn('id', $ourTeamIds)->delete();
        }
    }

    private function createCaptain(string $firstName, string $lastName): User
    {
        return User::firstOrCreate(
            ['email' => strtolower($firstName . '.' . $lastName) . '@demo.ctt.be'],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'is_competitor' => true,
                'is_active' => true,
                'has_paid' => true,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'club_id' => $this->club->id,
                'licence' => rand(95000, 170000),
                'ranking' => 'B4',
            ]
        );
    }

    private function createInterclub(Team $ourTeam, Team $opponentTeam, string $dateTime, bool $isHome): Interclub
    {
        $visitedId = $isHome ? $ourTeam->id : $opponentTeam->id;
        $visitingId = $isHome ? $opponentTeam->id : $ourTeam->id;

        $homeClub = $isHome ? $this->club : $opponentTeam->club;
        $homeAddress = $homeClub?->street ?? ($isHome ? 'Rue de l\'invasion 80, 1340 Ottignies' : 'Salle adverse');

        $interclub = Interclub::create([
            'visited_team_id' => $visitedId,
            'visiting_team_id' => $visitingId,
            'start_date_time' => Carbon::parse($dateTime),
            'address' => $homeAddress,
            'total_players' => $ourTeam->league?->category === 'MEN' ? 4 : 3,
            'week_number' => Carbon::parse($dateTime)->isoWeek(),
            'season_id' => $this->season->id,
            'league_id' => $ourTeam->league_id,
        ]);

        $this->syncMatchResult($ourTeam, $opponentTeam, $interclub, $isHome);

        return $interclub;
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
        $opponentClub = Club::firstOrCreate(
            ['name' => $clubName],
            [
                'licence' => 'OPP-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $clubName), 0, 8)),
                'city_name' => $clubName,
                'street' => $address,
            ]
        );

        if ($address && ! $opponentClub->street) {
            $opponentClub->update(['street' => $address]);
        }

        return Team::firstOrCreate(
            ['name' => $teamLetter, 'season_id' => $this->season->id, 'league_id' => $league->id, 'club_id' => $opponentClub->id],
        );
    }

    private function seedAvailabilityAndSelections(Team $team, array $interclubs): void
    {
        $players = $team->users()->get();

        if ($players->isEmpty()) {
            return;
        }

        $maxPlayers = $team->league?->category === 'MEN' ? 4 : 3;

        foreach ($interclubs as $index => $interclub) {
            foreach ($players as $player) {
                $random = rand(1, 10);
                $availability = match (true) {
                    $random <= 6 => InterclubAvailability::AVAILABLE,
                    $random <= 8 => InterclubAvailability::UNAVAILABLE,
                    default => InterclubAvailability::MAYBE,
                };
                $interclub->markAvailability($player, $availability);
            }

            if ($index === 0) {
                foreach ($interclub->getAvailablePlayers()->take($maxPlayers) as $player) {
                    $interclub->select($player);
                }
            }

            if ($index === 1) {
                foreach ($interclub->getAvailablePlayers()->take($maxPlayers - 1) as $player) {
                    $interclub->select($player);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MEN — 5 équipes, 9 adversaires chacune (18 matchs : 9 aller + 9 retour)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedMen(): void
    {
        $leagueA = $this->league('P2A', LeagueCategory::MEN);
        $leagueB = $this->league('P3B', LeagueCategory::MEN);
        $leagueC = $this->league('P3C', LeagueCategory::MEN);
        $leagueD = $this->league('P4A', LeagueCategory::MEN);
        $leagueE = $this->league('P5B', LeagueCategory::MEN);

        $captainA = User::firstWhere('email', 'aurelien.paulus@gmail.com') ?? $this->createCaptain('Jean', 'Dupont');
        $captainB = $this->createCaptain('Marc', 'Durand');
        $captainC = $this->createCaptain('Luc', 'Lambert');
        $captainD = $this->createCaptain('Pierre', 'Renard');
        $captainE = $this->createCaptain('Thomas', 'Laurent');

        $teamA = $this->team('A', $leagueA, $captainA);
        $teamB = $this->team('B', $leagueB, $captainB);
        $teamC = $this->team('C', $leagueC, $captainC);
        $teamD = $this->team('D', $leagueD, $captainD);
        $teamE = $this->team('E', $leagueE, $captainE);

        $this->assignPlayers($teamA, 7);
        $this->assignPlayers($teamB, 7);
        $this->assignPlayers($teamC, 7);
        $this->assignPlayers($teamD, 7);
        $this->assignPlayers($teamE, 7);

        // ── Équipe A (P2A) — 9 adversaires, vendredis 20h00
        $a1 = $this->opponentTeam('Arc En Ciel', 'F', $leagueA, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $a2 = $this->opponentTeam('TT Zénith Brussels', 'A', $leagueA, 'Rue de Birmingham 115, 1070 Anderlecht');
        $a3 = $this->opponentTeam('Braine-l\'Alleud', 'I', $leagueA, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');
        $a4 = $this->opponentTeam('Logis Auderghem', 'I', $leagueA, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $a5 = $this->opponentTeam('Gremlins', 'A', $leagueA, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $a6 = $this->opponentTeam('Tourinnes', 'A', $leagueA, 'Rue des Templiers 5, 1320 Beauvechain');
        $a7 = $this->opponentTeam('La Hulpe-Rixensart', 'C', $leagueA, 'Rue du Moulin 15, 1310 La Hulpe');
        $a8 = $this->opponentTeam('Set-Jet Fleur Bleue', 'D', $leagueA, 'Avenue du Comté de Jette 3, 1090 Jette');
        $a9 = $this->opponentTeam('Watermael-Boitsfort', 'A', $leagueA, 'Chaussée de Maelbeek 24, 1170 Watermael-Boitsfort');

        // Aller (automne 2026) — 9 vendredis consécutifs
        $interclubsA = [
            $this->createInterclub($teamA, $a1, '2026-09-11 20:00', true),
            $this->createInterclub($teamA, $a2, '2026-09-18 20:00', false),
            $this->createInterclub($teamA, $a3, '2026-09-25 20:00', true),
            $this->createInterclub($teamA, $a4, '2026-10-02 20:00', false),
            $this->createInterclub($teamA, $a5, '2026-10-09 20:00', true),
            $this->createInterclub($teamA, $a6, '2026-10-16 20:00', false),
            $this->createInterclub($teamA, $a7, '2026-10-23 20:00', true),
            $this->createInterclub($teamA, $a8, '2026-10-30 20:00', false),
            $this->createInterclub($teamA, $a9, '2026-11-06 20:00', true),
            // Retour (printemps 2027) — adversaires inversés domicile/extérieur
            $this->createInterclub($teamA, $a1, '2027-01-08 20:00', false),
            $this->createInterclub($teamA, $a2, '2027-01-15 20:00', true),
            $this->createInterclub($teamA, $a3, '2027-01-22 20:00', false),
            $this->createInterclub($teamA, $a4, '2027-01-29 20:00', true),
            $this->createInterclub($teamA, $a5, '2027-02-05 20:00', false),
            $this->createInterclub($teamA, $a6, '2027-02-12 20:00', true),
            $this->createInterclub($teamA, $a7, '2027-02-19 20:00', false),
            $this->createInterclub($teamA, $a8, '2027-02-26 20:00', true),
            $this->createInterclub($teamA, $a9, '2027-03-05 20:00', false),
        ];
        $this->seedAvailabilityAndSelections($teamA, array_slice($interclubsA, 0, 4));

        // ── Équipe B (P3B) — 9 adversaires, vendredis 20h00
        $b1 = $this->opponentTeam('Arc En Ciel', 'G', $leagueB, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $b2 = $this->opponentTeam('REP Nivellois', 'D', $leagueB, 'Rue des Heures Claires 46, 1400 Nivelles');
        $b3 = $this->opponentTeam('Piranha', 'G', $leagueB, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $b4 = $this->opponentTeam('Royal 1865 Waterloo', 'B', $leagueB, 'Boulevard de la Résistance 1, 1410 Waterloo');
        $b5 = $this->opponentTeam('TT Zénith Brussels', 'B', $leagueB, 'Rue de Birmingham 115, 1070 Anderlecht');
        $b6 = $this->opponentTeam('Logis Auderghem', 'N', $leagueB, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $b7 = $this->opponentTeam('La Hulpe-Rixensart', 'D', $leagueB, 'Rue du Moulin 15, 1310 La Hulpe');
        $b8 = $this->opponentTeam('CTT Limal', 'H', $leagueB, 'Rue Charles Jaumotte 156, 1300 Limal');
        $b9 = $this->opponentTeam('Watermael-Boitsfort', 'B', $leagueB, 'Chaussée de Maelbeek 24, 1170 Watermael-Boitsfort');

        $interclubsB = [
            $this->createInterclub($teamB, $b1, '2026-09-11 20:00', false),
            $this->createInterclub($teamB, $b2, '2026-09-18 20:00', true),
            $this->createInterclub($teamB, $b3, '2026-09-25 20:00', false),
            $this->createInterclub($teamB, $b4, '2026-10-02 20:00', true),
            $this->createInterclub($teamB, $b5, '2026-10-09 20:00', false),
            $this->createInterclub($teamB, $b6, '2026-10-16 20:00', true),
            $this->createInterclub($teamB, $b7, '2026-10-23 20:00', false),
            $this->createInterclub($teamB, $b8, '2026-10-30 20:00', true),
            $this->createInterclub($teamB, $b9, '2026-11-06 20:00', false),
            $this->createInterclub($teamB, $b1, '2027-01-08 20:00', true),
            $this->createInterclub($teamB, $b2, '2027-01-15 20:00', false),
            $this->createInterclub($teamB, $b3, '2027-01-22 20:00', true),
            $this->createInterclub($teamB, $b4, '2027-01-29 20:00', false),
            $this->createInterclub($teamB, $b5, '2027-02-05 20:00', true),
            $this->createInterclub($teamB, $b6, '2027-02-12 20:00', false),
            $this->createInterclub($teamB, $b7, '2027-02-19 20:00', true),
            $this->createInterclub($teamB, $b8, '2027-02-26 20:00', false),
            $this->createInterclub($teamB, $b9, '2027-03-05 20:00', true),
        ];
        $this->seedAvailabilityAndSelections($teamB, array_slice($interclubsB, 0, 4));

        // ── Équipe C (P3C) — 9 adversaires, samedis 14h00
        $c1 = $this->opponentTeam('CTT Limal', 'F', $leagueC, 'Rue Charles Jaumotte 156, 1300 Limal');
        $c2 = $this->opponentTeam('Safran', 'A', $leagueC, 'Rue Emile Francqui 1, 1140 Evere');
        $c3 = $this->opponentTeam('Braine-l\'Alleud', 'M', $leagueC, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');
        $c4 = $this->opponentTeam('Set-Jet Fleur Bleue', 'K', $leagueC, 'Avenue du Comté de Jette 3, 1090 Jette');
        $c5 = $this->opponentTeam('Mont-Saint-Guibert', 'B', $leagueC, 'Chaussée de Louvain 14, 1435 Mont-Saint-Guibert');
        $c6 = $this->opponentTeam('Beauchamp', 'A', $leagueC, 'Rue de la Victoire 5, 1400 Nivelles');
        $c7 = $this->opponentTeam('Witterzee', 'A', $leagueC, 'Rue Joseph Lacoste 3, 1332 Rixensart');
        $c8 = $this->opponentTeam('Logis Auderghem', 'O', $leagueC, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $c9 = $this->opponentTeam('TT Zénith Brussels', 'C', $leagueC, 'Rue de Birmingham 115, 1070 Anderlecht');

        $interclubsC = [
            $this->createInterclub($teamC, $c1, '2026-09-12 14:00', false),
            $this->createInterclub($teamC, $c2, '2026-09-19 14:00', true),
            $this->createInterclub($teamC, $c3, '2026-09-26 14:00', false),
            $this->createInterclub($teamC, $c4, '2026-10-03 14:00', true),
            $this->createInterclub($teamC, $c5, '2026-10-10 14:00', false),
            $this->createInterclub($teamC, $c6, '2026-10-17 14:00', true),
            $this->createInterclub($teamC, $c7, '2026-10-24 14:00', false),
            $this->createInterclub($teamC, $c8, '2026-10-31 14:00', true),
            $this->createInterclub($teamC, $c9, '2026-11-07 14:00', false),
            $this->createInterclub($teamC, $c1, '2027-01-09 14:00', true),
            $this->createInterclub($teamC, $c2, '2027-01-16 14:00', false),
            $this->createInterclub($teamC, $c3, '2027-01-23 14:00', true),
            $this->createInterclub($teamC, $c4, '2027-01-30 14:00', false),
            $this->createInterclub($teamC, $c5, '2027-02-06 14:00', true),
            $this->createInterclub($teamC, $c6, '2027-02-13 14:00', false),
            $this->createInterclub($teamC, $c7, '2027-02-20 14:00', true),
            $this->createInterclub($teamC, $c8, '2027-02-27 14:00', false),
            $this->createInterclub($teamC, $c9, '2027-03-06 14:00', true),
        ];
        $this->seedAvailabilityAndSelections($teamC, array_slice($interclubsC, 0, 4));

        // ── Équipe D (P4A) — 9 adversaires, samedis 20h00
        $d1 = $this->opponentTeam('CTT Le Moulin', 'C', $leagueD, 'Route du Moulin 4, 1380 Lasne');
        $d2 = $this->opponentTeam('TT Zénith Brussels', 'C', $leagueD, 'Rue de Birmingham 115, 1070 Anderlecht');
        $d3 = $this->opponentTeam('Gremlins', 'F', $leagueD, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $d4 = $this->opponentTeam('Piranha', 'I', $leagueD, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $d5 = $this->opponentTeam('Eveil', 'F', $leagueD, 'Rue du Frontispice 2, 1140 Evere');
        $d6 = $this->opponentTeam('Arc En Ciel', 'J', $leagueD, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $d7 = $this->opponentTeam('Smash Evere', 'C', $leagueD, 'Avenue de la Reine 31, 1140 Evere');
        $d8 = $this->opponentTeam('Braine-l\'Alleud', 'Q', $leagueD, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');
        $d9 = $this->opponentTeam('Arc En Ciel', 'K', $leagueD, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');

        $interclubsD = [
            $this->createInterclub($teamD, $d1, '2026-09-12 20:00', false),
            $this->createInterclub($teamD, $d2, '2026-09-19 20:00', true),
            $this->createInterclub($teamD, $d3, '2026-09-26 20:00', false),
            $this->createInterclub($teamD, $d4, '2026-10-03 20:00', true),
            $this->createInterclub($teamD, $d5, '2026-10-10 20:00', false),
            $this->createInterclub($teamD, $d6, '2026-10-17 20:00', true),
            $this->createInterclub($teamD, $d7, '2026-10-24 20:00', false),
            $this->createInterclub($teamD, $d8, '2026-10-31 20:00', true),
            $this->createInterclub($teamD, $d9, '2026-11-07 20:00', false),
            $this->createInterclub($teamD, $d1, '2027-01-09 20:00', true),
            $this->createInterclub($teamD, $d2, '2027-01-16 20:00', false),
            $this->createInterclub($teamD, $d3, '2027-01-23 20:00', true),
            $this->createInterclub($teamD, $d4, '2027-01-30 20:00', false),
            $this->createInterclub($teamD, $d5, '2027-02-06 20:00', true),
            $this->createInterclub($teamD, $d6, '2027-02-13 20:00', false),
            $this->createInterclub($teamD, $d7, '2027-02-20 20:00', true),
            $this->createInterclub($teamD, $d8, '2027-02-27 20:00', false),
            $this->createInterclub($teamD, $d9, '2027-03-06 20:00', true),
        ];
        $this->seedAvailabilityAndSelections($teamD, array_slice($interclubsD, 0, 4));

        // ── Équipe E (P5B) — 9 adversaires, jeudis 20h00 (bihebdomadaire)
        $e1 = $this->opponentTeam('CTT Alpa Schaerbeek', 'P', $leagueE, 'Rue Adolphe Demeure 16, 1030 Schaerbeek');
        $e2 = $this->opponentTeam('Tourinnes', 'E', $leagueE, 'Rue des Templiers 5, 1320 Beauvechain');
        $e3 = $this->opponentTeam('Braine-l\'Alleud', 'O', $leagueE, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');
        $e4 = $this->opponentTeam('Logis Auderghem', '2', $leagueE, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $e5 = $this->opponentTeam('CTT Limal', 'I', $leagueE, 'Rue Charles Jaumotte 156, 1300 Limal');
        $e6 = $this->opponentTeam('Royal Clabecq', 'G', $leagueE, 'Rue du Sart 2, 1480 Tubize');
        $e7 = $this->opponentTeam('Beauchamp', 'B', $leagueE, 'Rue de la Victoire 5, 1400 Nivelles');
        $e8 = $this->opponentTeam('Mont-Saint-Guibert', 'C', $leagueE, 'Chaussée de Louvain 14, 1435 Mont-Saint-Guibert');
        $e9 = $this->opponentTeam('La Hulpe-Rixensart', 'F', $leagueE, 'Rue du Moulin 15, 1310 La Hulpe');

        $interclubsE = [
            $this->createInterclub($teamE, $e1, '2026-09-10 20:00', false),
            $this->createInterclub($teamE, $e2, '2026-09-24 20:00', false),
            $this->createInterclub($teamE, $e3, '2026-10-08 20:00', false),
            $this->createInterclub($teamE, $e4, '2026-10-22 20:00', true),
            $this->createInterclub($teamE, $e5, '2026-11-05 20:00', true),
            $this->createInterclub($teamE, $e6, '2026-11-19 20:00', true),
            $this->createInterclub($teamE, $e7, '2026-12-03 20:00', false),
            $this->createInterclub($teamE, $e8, '2026-12-10 20:00', true),
            $this->createInterclub($teamE, $e9, '2026-12-17 20:00', false),
            $this->createInterclub($teamE, $e1, '2027-01-07 20:00', true),
            $this->createInterclub($teamE, $e2, '2027-01-21 20:00', true),
            $this->createInterclub($teamE, $e3, '2027-02-04 20:00', true),
            $this->createInterclub($teamE, $e4, '2027-02-18 20:00', false),
            $this->createInterclub($teamE, $e5, '2027-03-04 20:00', false),
            $this->createInterclub($teamE, $e6, '2027-03-18 20:00', false),
            $this->createInterclub($teamE, $e7, '2027-04-01 20:00', true),
            $this->createInterclub($teamE, $e8, '2027-04-15 20:00', false),
            $this->createInterclub($teamE, $e9, '2027-04-29 20:00', true),
        ];
        $this->seedAvailabilityAndSelections($teamE, array_slice($interclubsE, 0, 4));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VETERANS — 3 équipes, 9 adversaires chacune, dimanches
    // ─────────────────────────────────────────────────────────────────────────
    private function seedVeterans(): void
    {
        $leagueA = $this->league('3B', LeagueCategory::VETERANS);
        $leagueB = $this->league('3C', LeagueCategory::VETERANS);
        $leagueC = $this->league('4F', LeagueCategory::VETERANS);

        $captainA = User::firstWhere('email', 'aurelien.paulus@gmail.com') ?? $this->createCaptain('Pierre', 'Martin');
        $captainB = $this->createCaptain('André', 'Lecomte');
        $captainC = $this->createCaptain('Robert', 'Charlier');

        $teamA = $this->team('A', $leagueA, $captainA);
        $teamB = $this->team('B', $leagueB, $captainB);
        $teamC = $this->team('C', $leagueC, $captainC);

        $this->assignPlayers($teamA, 7);
        $this->assignPlayers($teamB, 7);
        $this->assignPlayers($teamC, 7);

        // ── Vét. A (3B) — 9 adversaires, dimanches 10h00
        $va1 = $this->opponentTeam('Uccle Ping', 'B', $leagueA, 'Chaussée de Waterloo 1475, 1180 Uccle');
        $va2 = $this->opponentTeam('CTT Limal', 'C', $leagueA, 'Rue Charles Jaumotte 156, 1300 Limal');
        $va3 = $this->opponentTeam('Piranha', 'C', $leagueA, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $va4 = $this->opponentTeam('Fonteny-Genappe', 'A', $leagueA, 'Rue du Centre 3, 1470 Genappe');
        $va5 = $this->opponentTeam('Set-Jet Fleur Bleue', 'C', $leagueA, 'Avenue du Comté de Jette 3, 1090 Jette');
        $va6 = $this->opponentTeam('Gremlins', 'B', $leagueA, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $va7 = $this->opponentTeam('TT Zénith Brussels', 'B', $leagueA, 'Rue de Birmingham 115, 1070 Anderlecht');
        $va8 = $this->opponentTeam('Piranha', 'D', $leagueA, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $va9 = $this->opponentTeam('Watermael-Boitsfort', 'A', $leagueA, 'Chaussée de Maelbeek 24, 1170 Watermael-Boitsfort');

        $interclubsVetA = [
            $this->createInterclub($teamA, $va1, '2026-10-04 10:00', true),
            $this->createInterclub($teamA, $va2, '2026-10-11 10:00', false),
            $this->createInterclub($teamA, $va3, '2026-10-18 10:00', true),
            $this->createInterclub($teamA, $va4, '2026-11-01 10:00', false),
            $this->createInterclub($teamA, $va5, '2026-11-08 10:00', true),
            $this->createInterclub($teamA, $va6, '2026-11-15 10:00', false),
            $this->createInterclub($teamA, $va7, '2026-11-22 10:00', true),
            $this->createInterclub($teamA, $va8, '2026-12-06 10:00', false),
            $this->createInterclub($teamA, $va9, '2026-12-13 10:00', true),
            $this->createInterclub($teamA, $va1, '2027-01-17 10:00', false),
            $this->createInterclub($teamA, $va2, '2027-01-24 10:00', true),
            $this->createInterclub($teamA, $va3, '2027-01-31 10:00', false),
            $this->createInterclub($teamA, $va4, '2027-02-07 10:00', true),
            $this->createInterclub($teamA, $va5, '2027-02-14 10:00', false),
            $this->createInterclub($teamA, $va6, '2027-02-21 10:00', true),
            $this->createInterclub($teamA, $va7, '2027-02-28 10:00', false),
            $this->createInterclub($teamA, $va8, '2027-03-07 10:00', true),
            $this->createInterclub($teamA, $va9, '2027-03-14 10:00', false),
        ];
        $this->seedAvailabilityAndSelections($teamA, array_slice($interclubsVetA, 0, 3));

        // ── Vét. B (3C) — 9 adversaires, dimanches 10h00
        $vb1 = $this->opponentTeam('Logis Auderghem', 'C', $leagueB, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $vb2 = $this->opponentTeam('Arc En Ciel', 'C', $leagueB, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $vb3 = $this->opponentTeam('TT Zénith Brussels', 'A', $leagueB, 'Rue de Birmingham 115, 1070 Anderlecht');
        $vb4 = $this->opponentTeam('Set-Jet Fleur Bleue', 'D', $leagueB, 'Avenue du Comté de Jette 3, 1090 Jette');
        $vb5 = $this->opponentTeam('Palette Bleue', 'A', $leagueB, 'Avenue Stalingrad 30, 1000 Bruxelles');
        $vb6 = $this->opponentTeam('Le Moulin', 'A', $leagueB, 'Route du Moulin 4, 1380 Lasne');
        $vb7 = $this->opponentTeam('CTT Limal', 'D', $leagueB, 'Rue Charles Jaumotte 156, 1300 Limal');
        $vb8 = $this->opponentTeam('Gremlins', 'C', $leagueB, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $vb9 = $this->opponentTeam('Braine-l\'Alleud', 'B', $leagueB, 'Drève des Alliés 2, 1420 Braine-l\'Alleud');

        $interclubsVetB = [
            $this->createInterclub($teamB, $vb1, '2026-10-04 10:00', false),
            $this->createInterclub($teamB, $vb2, '2026-10-11 10:00', true),
            $this->createInterclub($teamB, $vb3, '2026-10-18 10:00', false),
            $this->createInterclub($teamB, $vb4, '2026-11-01 10:00', true),
            $this->createInterclub($teamB, $vb5, '2026-11-08 10:00', false),
            $this->createInterclub($teamB, $vb6, '2026-11-15 10:00', true),
            $this->createInterclub($teamB, $vb7, '2026-11-22 10:00', false),
            $this->createInterclub($teamB, $vb8, '2026-12-06 10:00', true),
            $this->createInterclub($teamB, $vb9, '2026-12-13 10:00', false),
            $this->createInterclub($teamB, $vb1, '2027-01-17 10:00', true),
            $this->createInterclub($teamB, $vb2, '2027-01-24 10:00', false),
            $this->createInterclub($teamB, $vb3, '2027-01-31 10:00', true),
            $this->createInterclub($teamB, $vb4, '2027-02-07 10:00', false),
            $this->createInterclub($teamB, $vb5, '2027-02-14 10:00', true),
            $this->createInterclub($teamB, $vb6, '2027-02-21 10:00', false),
            $this->createInterclub($teamB, $vb7, '2027-02-28 10:00', true),
            $this->createInterclub($teamB, $vb8, '2027-03-07 10:00', false),
            $this->createInterclub($teamB, $vb9, '2027-03-14 10:00', true),
        ];
        $this->seedAvailabilityAndSelections($teamB, array_slice($interclubsVetB, 0, 3));

        // ── Vét. C (4F) — 9 adversaires, dimanches 14h00
        $vc1 = $this->opponentTeam('Logis Auderghem', 'D', $leagueC, 'Chaussée de Wavre 1690, 1160 Auderghem');
        $vc2 = $this->opponentTeam('Beauchamp', 'B', $leagueC, 'Rue de la Victoire 5, 1400 Nivelles');
        $vc3 = $this->opponentTeam('Mont-Saint-Guibert', 'B', $leagueC, 'Chaussée de Louvain 14, 1435 Mont-Saint-Guibert');
        $vc4 = $this->opponentTeam('Gremlins', 'D', $leagueC, 'Avenue Fond\'Roy 87, 1180 Uccle');
        $vc5 = $this->opponentTeam('Uccle Ping', 'C', $leagueC, 'Chaussée de Waterloo 1475, 1180 Uccle');
        $vc6 = $this->opponentTeam('Arc En Ciel', 'E', $leagueC, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $vc7 = $this->opponentTeam('REP Nivellois', 'A', $leagueC, 'Rue des Heures Claires 46, 1400 Nivelles');
        $vc8 = $this->opponentTeam('Piranha', 'E', $leagueC, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');
        $vc9 = $this->opponentTeam('La Hulpe-Rixensart', 'B', $leagueC, 'Rue du Moulin 15, 1310 La Hulpe');

        $interclubsVetC = [
            $this->createInterclub($teamC, $vc1, '2026-10-04 14:00', true),
            $this->createInterclub($teamC, $vc2, '2026-10-11 14:00', false),
            $this->createInterclub($teamC, $vc3, '2026-10-18 14:00', true),
            $this->createInterclub($teamC, $vc4, '2026-11-01 14:00', false),
            $this->createInterclub($teamC, $vc5, '2026-11-08 14:00', true),
            $this->createInterclub($teamC, $vc6, '2026-11-15 14:00', false),
            $this->createInterclub($teamC, $vc7, '2026-11-22 14:00', true),
            $this->createInterclub($teamC, $vc8, '2026-12-06 14:00', false),
            $this->createInterclub($teamC, $vc9, '2026-12-13 14:00', true),
            $this->createInterclub($teamC, $vc1, '2027-01-17 14:00', false),
            $this->createInterclub($teamC, $vc2, '2027-01-24 14:00', true),
            $this->createInterclub($teamC, $vc3, '2027-01-31 14:00', false),
            $this->createInterclub($teamC, $vc4, '2027-02-07 14:00', true),
            $this->createInterclub($teamC, $vc5, '2027-02-14 14:00', false),
            $this->createInterclub($teamC, $vc6, '2027-02-21 14:00', true),
            $this->createInterclub($teamC, $vc7, '2027-02-28 14:00', false),
            $this->createInterclub($teamC, $vc8, '2027-03-07 14:00', true),
            $this->createInterclub($teamC, $vc9, '2027-03-14 14:00', false),
        ];
        $this->seedAvailabilityAndSelections($teamC, array_slice($interclubsVetC, 0, 3));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WOMEN — 1 équipe, 9 adversaires, samedis 14h00 (bihebdomadaire)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedWomen(): void
    {
        $league = $this->league('2A', LeagueCategory::WOMEN);

        $captain = $this->createCaptain('Marie', 'Simon');
        $team = $this->team('A', $league, $captain);
        $this->assignPlayers($team, 7);

        $w1 = $this->opponentTeam('La Hulpe-Rixensart', 'A', $league, 'Rue du Moulin 15, 1310 La Hulpe');
        $w2 = $this->opponentTeam('REP Nivellois', 'A', $league, 'Rue des Heures Claires 46, 1400 Nivelles');
        $w3 = $this->opponentTeam('Royal 1865 Waterloo', 'A', $league, 'Boulevard de la Résistance 1, 1410 Waterloo');
        $w4 = $this->opponentTeam('TT Perwez', 'A', $league, 'Rue du Presbytère 5, 5310 Perwez');
        $w5 = $this->opponentTeam('CTT Limal', 'A', $league, 'Rue Charles Jaumotte 156, 1300 Limal');
        $w6 = $this->opponentTeam('Tourinnes', 'A', $league, 'Rue des Templiers 5, 1320 Beauvechain');
        $w7 = $this->opponentTeam('CTT Limal', 'B', $league, 'Rue Charles Jaumotte 156, 1300 Limal');
        $w8 = $this->opponentTeam('Arc En Ciel', 'A', $league, 'Avenue Urbain Britsiers 5, 1030 Schaerbeek');
        $w9 = $this->opponentTeam('Piranha', 'A', $league, 'Avenue de la Brabançonne 52, 1030 Schaerbeek');

        $interclubs = [
            $this->createInterclub($team, $w1, '2026-09-12 14:00', false),
            $this->createInterclub($team, $w2, '2026-09-26 14:00', true),
            $this->createInterclub($team, $w3, '2026-10-10 14:00', false),
            $this->createInterclub($team, $w4, '2026-10-24 14:00', true),
            $this->createInterclub($team, $w5, '2026-11-07 14:00', false),
            $this->createInterclub($team, $w6, '2026-11-21 14:00', true),
            $this->createInterclub($team, $w7, '2026-12-05 14:00', false),
            $this->createInterclub($team, $w8, '2026-12-12 14:00', true),
            $this->createInterclub($team, $w9, '2026-12-19 14:00', false),
            $this->createInterclub($team, $w1, '2027-01-09 14:00', true),
            $this->createInterclub($team, $w2, '2027-01-23 14:00', false),
            $this->createInterclub($team, $w3, '2027-02-06 14:00', true),
            $this->createInterclub($team, $w4, '2027-02-20 14:00', false),
            $this->createInterclub($team, $w5, '2027-03-06 14:00', true),
            $this->createInterclub($team, $w6, '2027-03-20 14:00', false),
            $this->createInterclub($team, $w7, '2027-04-03 14:00', true),
            $this->createInterclub($team, $w8, '2027-04-17 14:00', false),
            $this->createInterclub($team, $w9, '2027-05-01 14:00', true),
        ];
        $this->seedAvailabilityAndSelections($team, array_slice($interclubs, 0, 3));
    }

    private function syncMatchResult(Team $ourTeam, Team $opponentTeam, Interclub $interclub, bool $isHome): void
    {
        $opponentName = trim(($opponentTeam->club?->name ?? '') . ' ' . ($opponentTeam->name ?? ''));

        MatchResult::firstOrCreate(
            [
                'team_id' => $ourTeam->id,
                'season_id' => $this->season->id,
                'match_date' => $interclub->start_date_time->toDateString(),
            ],
            [
                'week_number' => $interclub->week_number,
                'is_home' => $isHome,
                'opponent_name' => $opponentName,
                'score' => null,
                'result' => null,
                'is_bye' => false,
            ]
        );
    }

    private function team(string $name, League $league, User $captain): Team
    {
        $team = Team::firstOrCreate(
            ['name' => $name, 'season_id' => $this->season->id, 'league_id' => $league->id, 'club_id' => $this->club->id],
            ['captain_id' => $captain->id]
        );

        $team->update(['captain_id' => $captain->id]);

        if (! $team->users()->where('users.id', $captain->id)->exists()) {
            $team->users()->syncWithoutDetaching([$captain->id]);
        }

        return $team;
    }
}
